<?php

namespace Drupal\flysystem\Controller;

use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\image\ImageStyleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Defines an image style controller that serves from temporary, then redirects.
 */
class ImageStyleRedirectController extends ImageStyleDownloadController {

  /**
   * The file entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fileStorage;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The image style copier.
   *
   * @var \Drupal\flysystem\ImageStyleCopier
   */
  protected $imageStyleCopier;

  /**
   * The mime type guesser.
   *
   * @var \Symfony\Component\Mime\MimeTypeGuesserInterface
   */
  protected $mimeTypeGuesser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->fileStorage = $container->get('entity_type.manager')->getStorage('file');
    $instance->fileSystem = $container->get('file_system');
    $instance->renderer = $container->get('renderer');
    $instance->imageStyleCopier = $container->get('flysystem.image_style_copier');
    $instance->mimeTypeGuesser = $container->get('file.mime_type.guesser');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function deliver(Request $request, $scheme, ImageStyleInterface $image_style) {
    $target = $request->query->get('file');
    $source_uri = $scheme . '://' . $target;

    $this->validateRequest($request, $image_style, $scheme, $target);

    // Don't try to generate file if source is missing.
    try {
      $source_uri = $this->validateSource($source_uri);
    }
    catch (FileNotFoundException $e) {
      $derivative_uri = $image_style->buildUri($source_uri);
      $this->logger->notice('Source image at %source_image_path not found while trying to generate derivative image at %derivative_path.', [
        '%source_image_path' => $source_uri,
        '%derivative_path' => $derivative_uri,
      ]);
      return new Response($this->t('Error generating image, missing source file.'), 404);
    }

    // If the image already exists on the adapter, deliver it instead.
    try {
      return $this->redirectAdapterImage($source_uri, $image_style);
    }
    catch (FileNotFoundException $e) {
      return $this->deliverTemporary($scheme, $target, $image_style);
    }
  }

  /**
   * Generate a temporary image for an image style.
   *
   * @param string $scheme
   *   The file scheme, defaults to 'private'.
   * @param string $source_path
   *   The image file to generate the temporary image for.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @throws \RuntimeException
   *   Thrown when generate() failed to generate an image.
   *
   * @return \Drupal\file\Entity\File
   *   The temporary image that was generated.
   */
  protected function generateTemporaryImage($scheme, $source_path, ImageStyleInterface $image_style) {
    // Remove any derivative extension from the source path.
    $derivative_extension = $image_style->getDerivativeExtension('');
    if ($derivative_extension) {
      $source_path = substr($source_path, 0, -strlen($derivative_extension) - 1);
    }

    $image_uri = "$scheme://$source_path";
    $destination_temp = $this->getTemporaryDestination($scheme, $source_path, $image_style);

    // Try to generate the temporary image, watching for other threads that may
    // also be trying to generate the temporary image.
    try {
      $success = $this->generate($image_style, $image_uri, $destination_temp);
      if (!$success) {
        throw new \RuntimeException('The temporary image could not be generated');
      }
    }
    catch (ServiceUnavailableHttpException $e) {
      // This exception is only thrown if the lock could not be acquired.
      $tries = 0;

      do {
        if (file_exists($destination_temp)) {
          break;
        }

        // The file still doesn't exist.
        usleep(250000);
        $tries++;
      } while ($tries < 4);

      // We waited for more than 1 second for the temporary image to appear.
      // Since local image generation should be fast, fail out here to try to
      // limit PHP process demands.
      if ($tries >= 4) {
        throw $e;
      }
    }

    return $destination_temp;
  }

  /**
   * Flushes the output buffer and copies the temporary images to the adapter.
   */
  protected function flushCopy() {
    // We have to call both of these to actually flush the image.
    Response::closeOutputBuffers(0, TRUE);
    flush();
    $this->imageStyleCopier->processCopyTasks();
  }

  /**
   * Redirects to an adapter hosted image, if it exists.
   *
   * @param string $source_uri
   *   The URI to the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to redirect to.
   *
   * @throws \Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException
   *   Thrown if the derivative does not exist on the adapter.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A redirect to the image if it exists.
   */
  protected function redirectAdapterImage($source_uri, ImageStyleInterface $image_style) {
    $derivative_uri = $image_style->buildUri($source_uri);

    if (file_exists($derivative_uri)) {
      // We can't just return TrustedRedirectResponse because core throws an
      // exception about missing cache metadata.
      // https://www.drupal.org/node/2638686
      // https://www.drupal.org/node/2630808
      // http://drupal.stackexchange.com/questions/187086/trustedresponseredirect-failing-how-to-prevent-cache-metadata
      $render_context = new RenderContext();
      $url = $this->renderer->executeInRenderContext($render_context, function () use ($image_style, $source_uri) {
        return Url::fromUri($image_style->buildUrl($source_uri))->toString();
      });

      $response = new TrustedRedirectResponse($url);
      if (!$render_context->isEmpty()) {
        $response->addCacheableDependency($render_context->pop());
      }

      return $response;
    }

    throw new FileNotFoundException(sprintf('%derivative_uri does not exist', $derivative_uri));
  }

  /**
   * Delivers a generate an image, deliver it, and upload it to the adapter.
   *
   * @param string $scheme
   *   The scheme of the source image.
   * @param string $source_path
   *   The path of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Symfony\Component\HttpFoundation\Response
   *   The image response, or an error response if image generation failed.
   */
  protected function deliverTemporary($scheme, $source_path, ImageStyleInterface $image_style) {
    $source_uri = $scheme . '://' . $source_path;

    // Try to serve the temporary image if possible. Load into memory, since it
    // can be deleted at any point.
    $destination_temp = $this->getTemporaryDestination($scheme, $source_path, $image_style);

    if (file_exists($destination_temp)) {
      return $this->sendRawImage($destination_temp);
    }

    try {
      $temporary_uri = $this->generateTemporaryImage($scheme, $source_path, $image_style);
    }
    catch (\RuntimeException $e) {
      $derivative_uri = $image_style->buildUri($source_uri);
      $this->logger->notice('Unable to generate the derived image located at %path.', ['%path' => $derivative_uri]);
      return new Response($this->t('Error generating image.'), 500);
    }

    // Register a copy task with the kernel terminate handler.
    $this->imageStyleCopier->addCopyTask($temporary_uri, $source_uri, $image_style);

    // Symfony's kernel terminate handler is documented to only executes after
    // flushing with fastcgi, and not with mod_php or regular CGI. However,
    // it appears to work with mod_php. We assume it doesn't and register a
    // shutdown handler unless we know we are under fastcgi. If images have
    // been previously flushed and uploaded, this call will do nothing.
    //
    // https://github.com/symfony/symfony-docs/issues/6520
    if (!function_exists('fastcgi_finish_request')) {
      drupal_register_shutdown_function(function () {
        $this->flushCopy();
      });
    }

    return $this->send($scheme, $temporary_uri);
  }

  /**
   * Returns the temporary image path.
   *
   * @param string $scheme
   *   The scheme of the source image.
   * @param string $source_path
   *   The path of the source image.
   * @param \Drupal\image\ImageStyleInterface $image_style
   *   The image style to generate.
   *
   * @return string
   *   The temporary image path.
   */
  protected function getTemporaryDestination($scheme, $source_path, ImageStyleInterface $image_style) {
    return $image_style->buildUri("temporary://flysystem/$scheme/$source_path");
  }

  /**
   * Returns a response of the derived raw image.
   *
   * @param string $path
   *   The file path.
   * @param array $headers
   *   (optional) An array of headers to return in the response.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A response with the derived image.
   */
  protected function sendRawImage(string $path, array $headers = []): Response {
    $response = new BinaryFileResponse($path, 200, $headers);
    $response->headers->set('Content-Type', $this->mimeTypeGuesser->guessMimeType($path));

    return $response;
  }

}
