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

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    if (!$nodeEntity = $nodeStorage->load($node)) {
      return new JsonResponse(['error' => 'Invalid node ID.'], 400);
    }

    if($version == "2") {
      // Assume that the node has fields 'title', 'description', and 'image_url'.
      $title = $nodeEntity->get('title')->value;

      // Check if the node has 'description' field.
      $description = $nodeEntity->hasField('description') && !$nodeEntity->get('description')->isEmpty() 
      ? $nodeEntity->get('description')->value 
      : '';

      // Check if the node has 'image_url' field.
      $imageUrl = $nodeEntity->hasField('image_url') && !$nodeEntity->get('image_url')->isEmpty() 
      ? $nodeEntity->get('image_url')->value 
      : '';

      $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';

      $manifest = [
        '@context' => 'http://iiif.io/api/presentation/2/context.json',
        '@type' => 'sc:Manifest',
        '@id' => $protocol . "://" . $_SERVER['HTTP_HOST'] . "/iiif/{$version}/{$node}/manifest",
        'label' => $title,
        'description' => $description,
        'sequences' => [
          [
            '@type' => 'sc:Sequence',
            'canvases' => [
              [
                '@type' => 'sc:Canvas',
                'images' => [
                  [
                    '@type' => 'oa:Annotation',
                    'motivation' => 'sc:painting',
                    'resource' => [
                      '@type' => 'dctypes:Image',
                      '@id' => $imageUrl,
                      'format' => 'image/jpeg',
                      'service' => [
                        '@context' => 'http://iiif.io/api/image/2/context.json',
                        '@id' => $imageUrl,
                        'profile' => 'http://iiif.io/api/image/2/level1.json',
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ];

    } elseif($version == "3") {
      $manifest["@context"] = "http://iiif.io/api/presentation/3/context.json";
    } else {
      return new JsonResponse(['error' => 'Invalid version.'], 400);
    }

    return new JsonResponse($manifest);
  }
}