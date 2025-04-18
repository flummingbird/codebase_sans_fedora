/**
 * @file
 * Provides JavaScript additions to the S3 CORS upload managed file field type.
 */

(function($, Drupal, once) {
  /**
   * S3 File upload utility functions.
   *
   * @namespace
   */
  Drupal.flysystemS3 = Drupal.flysystemS3 || {
    /**
     * Submit file via CORS.
     *
     * @name Drupal.flysystemS3.submitCorsUpload
     *
     * @param {jQuery.Event} event
     *   The event triggered, most likely a `change` event.
     */
    submitCorsUpload(event) {
      const $fileElement = $(event.target);

      if (typeof $fileElement[0].files === "undefined") {
        return;
      }

      // Prevent the submit button from actually submitting.
      event.preventDefault();

      if (!Drupal.flysystemS3.validateFileExtension($fileElement)) {
        // Cancel all other submit event handlers.
        event.stopImmediatePropagation();
        return;
      }

      // Get the filelist and the number of files to be uploaded.
      const filelist = $fileElement[0].files;

      // Store uploaded files fid values.
      const uploadedFileFid = [];

      Object.keys(filelist).forEach(function(file) {
        const file_obj = filelist[file];

        const $progressBar = Drupal.flysystemS3.addProgressBar(
          $fileElement,
          file_obj
        );

        Drupal.flysystemS3
          .requestSignature($fileElement, file_obj)
          .fail(function() {
            Drupal.flysystemS3.setCorsUploadProgress(
              $progressBar,
              1,
              Drupal.t(
                "Signing request failed. Trying secondary upload method..."
              )
            );
            // Trigger the submit button to let normal AJAX process the upload.
            Drupal.file.triggerUploadButton(event);
          })
          .done(function(signedFormData) {
            Drupal.flysystemS3.setCorsUploadProgress(
              $progressBar,
              1,
              Drupal.t("Uploading @file", { "@file": file_obj.name })
            );

            Drupal.flysystemS3
              .uploadToAws(file_obj, signedFormData, $progressBar)
              .fail(function() {
                Drupal.flysystemS3.setCorsUploadProgress(
                  $progressBar,
                  1,
                  Drupal.t("Upload failed. Trying secondary upload method...")
                );
                // Trigger the submit button to let normal AJAX process the upload.
                Drupal.file.triggerUploadButton(event);
              })
              .done(function() {
                // Set progress bar to 100% in case the upload was so fast.
                Drupal.flysystemS3.setCorsUploadProgress(
                  $progressBar,
                  100,
                  Drupal.t("Processing upload")
                );

                Drupal.flysystemS3
                  .saveFile(signedFormData.url, file_obj)
                  .fail(function() {
                    Drupal.flysystemS3.setCorsUploadProgress(
                      $progressBar,
                      1,
                      Drupal.t(
                        "Signing request failed. Trying secondary upload method..."
                      )
                    );
                    // Trigger the submit button to let normal AJAX process the upload.
                    Drupal.file.triggerUploadButton(event);
                  })
                  .done(function(saveFileData) {
                    // Add the fid for this file to array.
                    uploadedFileFid.push(saveFileData.fid);

                    // Post the results to Drupal if all files have been processed.
                    const num_fids = uploadedFileFid.length;

                    if (num_fids == filelist.length) {
                      // Set the file upload to an empty value to prevent the file from being uploaded to Drupal.
                      $fileElement.val("");
                      // Set the fid element to our provided fid so that the AJAX response will render our file.
                      const $fidsElement = $fileElement
                        .closest(".js-form-managed-file")
                        .find('input[type="hidden"][name$="[fids]"]');
                      // List all uploaded files fids to string.
                      const uploadedFileFidString = uploadedFileFid.join(" ");

                      $fidsElement.val(uploadedFileFidString);

                      // Trigger the submit button to let normal AJAX process the upload.
                      Drupal.file.triggerUploadButton(event);
                    }
                  });
              });
          });
      });
    },

    /**
     * Adds a progress bar.
     */
    addProgressBar($fileElement, file_obj) {
      // Hide the upload field and the description.
      $fileElement.hide();
      $fileElement.siblings(".description").hide();

      const field_id = $fileElement.attr("id").replace(/\-upload/, "");

      const $progressBar = $(Drupal.theme.progressBar(`${field_id}-progress`));
      Drupal.flysystemS3.setCorsUploadProgress(
        $progressBar,
        0,
        Drupal.t("Signing @file for upload", { "@file": file_obj.name })
      );

      $fileElement.after($progressBar);

      return $progressBar;
    },

    /**
     * Receives an XMLHttpRequestProgressEvent to display current progress.
     *
     * @name Drupal.flysystemS3.processCorsUploadProgress
     *
     * @param {jQuery} $progressBar
     *   The progressbar element.
     * @param event
     *   And XMLHttpRequestProgressEvent object.
     */
    processCorsUploadProgress($progressBar, event) {
      if (event.lengthComputable) {
        // This is copied mostly from Drupal.ProgressBar.setProgress.
        const percentage = Math.floor((event.loaded / event.total) * 100);
        if (percentage >= 0 && percentage <= 100) {
          Drupal.flysystemS3.setCorsUploadProgress($progressBar, percentage);
        }
        return true;
      }
    },

    /**
     * Retrieves S3 signature for CORS upload, and a unique filename.
     *
     * @name Drupal.flysystemS3.requestSignature
     *
     * @param {jQuery} $fileElement
     *   The file field element.
     * @param file
     *   The file object to be uploaded.
     */
    requestSignature($fileElement, file) {
      // Use the file object and ask Drupal to generate the appropriate signed
      // request for us.
      const signingPostData = {
        filename: file.name,
        "Content-Type": file.type,
        acl: $fileElement.attr("data-s3-acl"),
        destination: $fileElement.attr("data-s3-destination")
      };

      // POST to Drupal which will return the required parameters for signing
      // a CORS request.
      return $.ajax({
        url: `${drupalSettings.path.baseUrl}flysystem-s3/cors-upload-sign`,
        data: signingPostData,
        type: "POST"
      });
    },

    /**
     * Post file data to Drupal to generate a file object.
     *
     * @param url
     *   The full file url, including streamwrapper.
     * @param file
     *   The file object.
     */
    saveFile(url, file) {
      const postData = {
        url,
        filename: file.name,
        filesize: file.size,
        filemime: file.type
      };

      // POST to Drupal which will save the uploaded file to Drupal.
      return $.ajax({
        url: `${drupalSettings.path.baseUrl}flysystem-s3/cors-upload-save`,
        data: postData,
        type: "POST"
      });
    },

    /**
     * Update the CORS progress bar with a percent and an optional label.
     *
     * @name Drupal.flysystemS3.setCorsUploadProgress
     *
     * @param {jQuery} $progressBar
     *   The progressbar element.
     * @param {string} percentage
     *   A percentage between or including 0 to 100.
     * @param {string} [label]
     *   An optional label
     */
    setCorsUploadProgress($progressBar, percentage, label) {
      $progressBar.find("div.progress__bar").css("width", `${percentage}%`);
      $progressBar.find("div.progress__percentage").html(`${percentage}%`);
      if (label) {
        $progressBar.find("div.progress__label").html(label);
      }
    },

    uploadToAws(file, signedFormData, $progressBar) {
      // Take the signed data and construct a form out of it.
      const uploadFormData = new FormData();
      $.each(signedFormData.inputs, function(key, value) {
        uploadFormData.append(key, value);
      });

      // Add the file to be uploaded.
      uploadFormData.append("file", file);

      return $.ajax({
        url: signedFormData.attributes.action,
        data: uploadFormData,
        type: signedFormData.attributes.method,
        mimeType: signedFormData.attributes.enctype,
        xhrFields: {
          withCredentials: true
        },
        cache: false,
        contentType: false,
        processData: false,
        xhr() {
          const myXhr = $.ajaxSettings.xhr();
          if (myXhr.upload) {
            myXhr.upload.addEventListener(
              "progress",
              function(event) {
                Drupal.flysystemS3.processCorsUploadProgress(
                  $progressBar,
                  event
                );
              },
              false
            );
          }
          return myXhr;
        }
      });
    },

    /**
     * Adds client side validation for the input[type=file].
     */
    validateFileExtension($fileElement) {
      // @todo Figure out why Drupal.file.validateFileExtension is not getting triggered.
      // @todo Figure out what additional validation should be run.
      // @see https://www.drupal.org/node/2235977
      if (!$fileElement.data("valid-extensions")) {
        return true;
      }

      $(".file-upload-js-error").remove();
      const extensionPattern = $fileElement
        .data("valid-extensions")
        // Convert commas and spaces to pipes.
        .replace(/,|\s+/g, "|")
        // Remove leading and trailing pipes.
        .replace(/^\|+|\|+$/, "");

      if (extensionPattern.length === 0) {
        return true;
      }

      const acceptableMatch = new RegExp(`\\.(${extensionPattern})$`, "i");

      const files = $.grep($fileElement[0].files, function(file) {
        return !acceptableMatch.test(file.name);
      });

      if (files.length === 0) {
        return true;
      }

      $.each(files, function(key, file) {
        const error = Drupal.t(
          "The selected file %filename cannot be uploaded. Only files with the following extensions are allowed: %extensions.",
          {
            "%filename": file.name,
            "%extensions": extensionPattern.replace(/\|/g, ", ")
          }
        );

        $fileElement
          .closest("div.js-form-managed-file")
          .prepend(
            `<div class="messages messages--error file-upload-js-error" aria-live="polite">${error}</div>`
          );
      });

      return false;
    }
  };

  /**
   * Attach behaviors to submit uploads via CORS.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches triggers for the upload button.
   * @prop {Drupal~behaviorDetach} detach
   *   Detaches auto file upload trigger.
   */
  Drupal.behaviors.flySystemS3CorsUpload = {
    attach(context) {
      const fileInputSelector =
        '.js-form-managed-file input[type="file"][data-flysystem-s3-cors]';
      $(once("auto-cors-upload", fileInputSelector, context))
        // Add the CORS upload handler to the file input.
        .on("change.autoCorsFileUpload", Drupal.flysystemS3.submitCorsUpload)
        // Disable the upload button trigger so that the CORS upload handler can run first.
        .off("change.autoFileUpload", Drupal.file.triggerUploadButton);
    },
    detach(context, setting, trigger) {
      const fileInputSelector = '.js-form-managed-file input[type="file"][data-flysystem-s3-cors]';
      if (trigger === "unload") {
        $(once.remove("auto-cors-upload", fileInputSelector, context))
          .off("change.autoCorsFileUpload", Drupal.flysystemS3.submitCorsUpload);
      }
    }
  };
})(jQuery, Drupal, once, drupalSettings);
