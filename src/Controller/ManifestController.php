<?php
// src/Controller/ManifestController.php
namespace Drupal\iiif_manifest\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class ManifestController extends ControllerBase {
  public function generateManifest($version, $node) {

    // TODO: Implement logic to generate manifest based on the node.
    // You may need to use other Drupal APIs or custom code to do so.
    $manifest = [];  // Replace with actual manifest.

    if($version == "2") {
      $manifest["@context"] = "http://iiif.io/api/presentation/2/context.json";
    } elseif($version == "3") {
      $manifest["@context"] = "http://iiif.io/api/presentation/3/context.json";
    } else {
      return new JsonResponse(['error' => 'Invalid version.'], 400);
    }

    return new JsonResponse($manifest);
  }
}