# AI-driven Recipe Scanner and Importer with Mealie / Nextcloud Cookbook support

A lightweight web tool to upload images of typed and handwritten reciped, perform OCR, convert the OCR text with AI to a Schema.org-Recipe-JSON and import the data into Mealie or Nextcloud Cookbook. Supports camera capture and file upload.

---

## Features

- Upload recipe images via file or camera  
- Preview before import
- OCR integration (Google Vision API)  
- JSON Generation from OCR text (Google Gemini API)

---

## Quick Setup

1. Copy project to your web server 
2. Configure API keys and Recipe Manager:

   - **Google Vision API**: Create a project in [Google Cloud Console](https://console.cloud.google.com/), enable Vision API, create a service account, and save the key in `credentials.original.php`.  
   - **Gemini API**: Sign up at [Gemini Developers](https://gemini.com/developers), generate an API key and save the key in `credentials.original.php`.
   - **Choose Recipe Manager**: Choose recipe manager like its described in `credentials.original.php`.
   - **Configure Recipe Manager for automatical import**: Configure your recipe manager-instance in `credentials.original.php`. Therefore insert your instance url and username and password for Mealie or username and token for Nextcloud Cookbook.
   - **Rename credentials-File**: Rename the file `credentials.original.php` into `credentials.php`.
3. Open index.php to start uploading and importing recipes! 

---

## License

Mozilla Public License Version 2.0, see LICENSE-file in this repository.

## Support

If you have any questions regarding this project, feel free to email me: [model_railroader@gmx-topmail.de](mailto:model_railroader@gmx-topmail.de)
