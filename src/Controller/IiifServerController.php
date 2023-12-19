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

      // entity.repository サービスを取得
      $entityRepository = \Drupal::service('entity.repository');

      // UUIDを使用してノードエンティティをロード
      $nodeEntity = $entityRepository->loadEntityByUuid('node', $node);
      if (!$nodeEntity) {
        return new JsonResponse(['error' => 'Invalid node UUID.'], 400);
      }

      // return new JsonResponse(['error' => 'Invalid node ID.'], 400);
    }

    // ノードのUUIDを取得
    $uuid = $nodeEntity->uuid();

    // ノードのタイプを取得
    $nodeType = $nodeEntity->getType();

    if($version == "2") {
      // Assume that the node has fields 'title', 'description', and 'field_iiif_image_url'.
      $title = $nodeEntity->get('title')->value;

      $config = \Drupal::config('iiif_server.settings');
      $descriptionField = $config->get('description_field');

      $iiifserver_manifest_attribution_default = $config->get('iiifserver_manifest_attribution_default');

      $iiifserver_manifest_rights_text = $config->get('iiifserver_manifest_rights_text');

      // Check if the node has 'description' field.
      $description = $nodeEntity->hasField($descriptionField) && !$nodeEntity->get($descriptionField)->isEmpty() 
      ? $nodeEntity->get($descriptionField)->value 
      : '';

      // 現在のURLを取得し、'/iiif/' で分割
      $currentUrlParts = explode('/iiif/', $_SERVER['REQUEST_URI'], 2);
      $baseUrl = $currentUrlParts[0];
      
      // $prefixの生成
      $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
      $prefix = $protocol . "://" . $_SERVER['HTTP_HOST'] . $baseUrl . "/iiif/{$version}/{$node}/";

      $metadata = $this->getMetadata($nodeEntity);

      $manifest = [
        '@context' => 'http://iiif.io/api/presentation/2/context.json',
        '@id' => $prefix . 'manifest',
        '@type' => 'sc:Manifest',
        'label' => $title,
        'description' => $description,
        'license' => null,
        'attribution' => $iiifserver_manifest_attribution_default,
        "seeAlso" => [
          "@id" => $protocol . "://" . $_SERVER['HTTP_HOST'] . $baseUrl . "/jsonapi/node/" . $nodeType . "/" . $uuid,
          "format" => "application/ld+json"
        ],
        'metadata' => $metadata,
        'sequences' => [
          [
            '@id' => $prefix . 'sequence/normal',
            '@type' => 'sc:Sequence',
            "label" => "Current Page Order",
            'canvases' => [],
          ],
        ],
      ];

      if ($iiifserver_manifest_rights_text) {
        $manifest['license'] = $iiifserver_manifest_rights_text;
      }

      if ($nodeEntity->hasField('field_iiif_images') && !$nodeEntity->get('field_iiif_images')->isEmpty()) {
        foreach ($nodeEntity->get('field_iiif_images') as $index => $fieldItem) {
          $num = $index + 1;
    
          // 各画像のURLと幅を取得
          $uri = $fieldItem->entity->field_iiif_image_url->uri;

          $serviceId = str_replace('/info.json', '', $uri);

          $imageUrl = str_replace('info.json', 'full/full/0/default.jpg', $uri);
          $thumbnailUrl = str_replace('info.json', 'full/!200,200/0/default.jpg', $uri);

          $width = intval($fieldItem->entity->field_iiif_image_width->value);
          $height = intval($fieldItem->entity->field_iiif_image_height->value); // 高さも同様に取得
    
          $canvas_id = $prefix . 'canvas/p' . $num;
    
          // Canvasの生成
          $canvas = [
            '@id' => $canvas_id,
            '@type' => 'sc:Canvas',
            'label' => '[' . $num . ']',
            'width' => $width,
            'height' => $height,
            'thumbnail' => [
              '@id' => $thumbnailUrl
            ],
            'images' => [
              [
                "@id" => $canvas_id . '/annotation',
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'resource' => [
                  '@id' => $imageUrl,
                  '@type' => 'dctypes:Image',
                  'format' => 'image/jpeg',
                  'width' => $width,
                  'height' => $height,
                  "service" => [
                    "@context" => "http://iiif.io/api/image/2/context.json",
                    "@id" => $serviceId,
                    "profile" => "http://iiif.io/api/image/2/level2.json",
                    "width" => $width,
                    "height" => $height
                  ]
                ],
                'on' => $canvas_id,
              ],
            ],
          ];
          $manifest['sequences'][0]['canvases'][] = $canvas;
        }
      }
    
    } elseif ($version == "3") {
      // IIIFバージョン3の処理...
      $manifest["@context"] = "http://iiif.io/api/presentation/3/context.json";
    } else {
      return new JsonResponse(['error' => 'Invalid version.'], 400);
    }

    return new JsonResponse($manifest);
  }

  /**
   * ノードエンティティからメタデータを取得します。
   *
   * @param \Drupal\node\NodeInterface $nodeEntity ノードエンティティ
   * @return array メタデータの配列
   */
  private function getMetadata($nodeEntity) {
    // エンティティタイプマネージャーを使用して、フィールド定義を取得
    $fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $nodeType);

    // フィールドのキーとラベルを格納する配列
    $metadata = [];

    foreach ($fieldDefinitions as $fieldName => $definition) {
      // フィールドのキー（フィールド名）とラベルを取得

      // フィールド名が 'field_' で始まるかチェック
      if (strpos($fieldName, 'field_') === 0 && $nodeEntity->hasField($fieldName)) {

        $metadata[] = [
          'label' => $definition->getLabel(),
          'value' => $nodeEntity->get($fieldName)->value,
        ];
      }
    }

    return $metadata;
  }
}