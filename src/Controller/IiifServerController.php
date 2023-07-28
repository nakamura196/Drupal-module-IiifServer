<?php
// src/Controller/IiifServerController.php
namespace Drupal\iiif_server\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class IiifServerController extends ControllerBase {
  public function generateManifest($version, $node) {

    // TODO: Implement logic to generate manifest based on the node.
    // You may need to use other Drupal APIs or custom code to do so.
    $manifest = [];  // Replace with actual manifest.

    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    if (!$nodeEntity = $nodeStorage->load($node)) {
      return new JsonResponse(['error' => 'Invalid node ID.'], 400);
    }

    if($version == "2") {
      // Assume that the node has fields 'title', 'description', and 'field_image_url'.
      $title = $nodeEntity->get('title')->value;

      $config = \Drupal::config('iiif_server.settings');
      $descriptionField = $config->get('description_field');

      // Check if the node has 'description' field.
      $description = $nodeEntity->hasField($descriptionField) && !$nodeEntity->get($descriptionField)->isEmpty() 
      ? $nodeEntity->get($descriptionField)->value 
      : '';

      // Check if the node has 'field_image_url' field.
      $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
      $prefix = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/iiif/{$version}/{$node}/";

      $manifest = [
        '@context' => 'http://iiif.io/api/presentation/2/context.json',
        '@type' => 'sc:Manifest',
        '@id' => $prefix . 'manifest',
        'label' => $title,
        'description' => $description,
        'sequences' => [
          [
            '@type' => 'sc:Sequence',
            'canvases' => [],
          ],
        ],
      ];

      if ($nodeEntity->hasField('field_image_url') && !$nodeEntity->get('field_image_url')->isEmpty()) {
        foreach ($nodeEntity->get('field_image_url') as $index => $field) {

          $num = $index + 1;
 
          $imageUrl = $field->value;
          $width = $nodeEntity->hasField('field_image_width') && !$nodeEntity->get('field_image_width')->isEmpty() 
            ? intval($nodeEntity->get('field_image_width')[$index]->value) 
            : 0;
          $height = $nodeEntity->hasField('field_image_height') && !$nodeEntity->get('field_image_height')->isEmpty() 
            ? intval($nodeEntity->get('field_image_height')[$index]->value) 
            : 0;
          $canvas = [
            '@type' => 'sc:Canvas',
            '@id' => $prefix . 'canvas/p' . $num,
            'width' => $width,
            'height' => $height,
            'thumbnail' => [
              '@id' => $imageUrl,
              "@type" => "dctypes:Image",
              "format" => "image/jpeg",
              "width" => $width,
              "height" => $height,
            ],
            'images' => [
              [
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'resource' => [
                  '@type' => 'dctypes:Image',
                  '@id' => $imageUrl,
                  'width' => $width,
                  'height' => $height,
                  'format' => 'image/jpeg'
                ],
                'on' => $prefix . 'canvas/p' . $num,
              ],
            ],
          ];
          $manifest['sequences'][0]['canvases'][] = $canvas;
        }
      }

    } elseif($version == "3") {
      $manifest["@context"] = "http://iiif.io/api/presentation/3/context.json";
    } else {
      return new JsonResponse(['error' => 'Invalid version.'], 400);
    }

    return new JsonResponse($manifest);
  }
}