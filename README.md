# IIIF Server

## Overview

IIIF Server is a custom Drupal module designed to integrate the IIIF specifications into your Drupal site.

## Features

-	IIIF Integration: Seamlessly integrates IIIF specifications into Drupal.

## Requirements

- Drupal: Version 10 or later.
- PHP: Ensure your PHP version meets Drupal’s requirements.
- IIIF-Compatible Media Server: Optional but recommended for advanced IIIF functionality.

## Installation

-	Place the iiif_server module folder in your modules/custom directory.
-	Enable the module using the Drupal admin interface or Drush:

```bash
drush en iiif_server
```

-	Clear the cache:

```bash
drush cr
```

## Usage

- Navigate to the module’s configuration page (/admin/config/iiif_server).

## Troubleshooting

- Ensure that your server meets all requirements.
- Check the Drupal logs (/admin/reports/dblog) for any errors.

## Contributing

If you’d like to contribute to the development of this module, please create a pull request or report issues in the repository.

## License

This module is licensed under the [Apache License, Version 2.0](LICENSE).

## Acknowledgments

- This module leverages the IIIF API specifications.
- Inspired by the need for robust media handling in Drupal.
