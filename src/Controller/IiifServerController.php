<?php
// src/Controller/IiifServerController.php
namespace Drupal\iiif_server\Controller;

// 必要な名前空間を use 文で明示的に指定
use Drupal\node\NodeInterface;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

use Drupal\iiif_server\View\Helper\IiifManifest3;

class IiifServerController extends ControllerBase {
  public function generateManifest($version, $node) {

    if ($version != "2" && $version != "3") {
        return new JsonResponse(['error' => 'Unsupported IIIF version.'], 400);
    }

    $nodeEntity = $this->loadNodeEntityByUuidOrId($node);
    if (!$nodeEntity) {
        return new JsonResponse(['error' => 'Invalid node identifier.'], 400);
    }

    $baseUrl = $this->getBaseUrl();
    $protocol = $this->getRequestProtocol();

    $prefix = $this->generatePrefix($protocol, $baseUrl, $version, $node);

    if ($version == "3") {
      // Instantiate the IiifManifest3 class
      $manifestBuilder = new IiifManifest3();

      $manifest = $manifestBuilder->buildManifestVersion3($nodeEntity, $prefix);

      return new JsonResponse($manifest);
    }

    

    
    
    $manifest = $this->buildManifest($version, $nodeEntity, $prefix);

    return new JsonResponse($manifest);
  }

  private function loadNodeEntityByUuidOrId($node) {
    $nodeEntity = \Drupal::entityTypeManager()->getStorage('node')->load($node);
    if (!$nodeEntity) {
        $nodeEntity = \Drupal::service('entity.repository')->loadEntityByUuid('node', $node);
    }
    return $nodeEntity;
  }

  private function getBaseUrl() {
      $currentUrlParts = explode('/iiif/', $_SERVER['REQUEST_URI'], 2);
      return $currentUrlParts[0];
  }

  private function getRequestProtocol() {
    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'http';
  }

  private function generatePrefix($protocol, $baseUrl, $version, $node) {
      return $protocol . "://" . $_SERVER['HTTP_HOST'] . $baseUrl . "/iiif/{$version}/{$node}/";
  }



  private function buildManifest($version, NodeInterface $nodeEntity, $prefix) {
    // Implementing IIIF version specific logic...
    if ($version == "2") {
        return $this->buildManifestVersion2($nodeEntity, $prefix);
    } elseif ($version == "3") {
        // Version 3 specific logic...
        return [];
    } else {
        throw new \InvalidArgumentException("Unsupported IIIF version.");
    }
  }

  private function buildManifestVersion2(NodeInterface $nodeEntity, $prefix) {
    $title = $nodeEntity->label();  // Assuming the 'title' field exists and is accessible this way.

    $config = \Drupal::config('iiif_server.settings');
    

    $metadata = $this->getMetadata($nodeEntity);

    $iiifserver_manifest_attribution_default = $config->get('iiifserver_manifest_attribution_default');    

    $iiifserver_field = $config->get("iiifserver_field");

    $sequence = [
      '@id' => $prefix . 'sequence/normal',
      '@type' => 'sc:Sequence',
      "label" => "Current Page Order",
      'canvases' => $this->getCanvases($nodeEntity, $iiifserver_field, $prefix),
    ];

    $iiifserver_manifest_viewing_direction_property = $config->get('iiifserver_manifest_viewing_direction_property');

    if($iiifserver_manifest_viewing_direction_property) {
      $sequence["viewingDirection"] = $nodeEntity->hasField($iiifserver_manifest_viewing_direction_property) && !$nodeEntity->get($iiifserver_manifest_viewing_direction_property)->isEmpty() ? $nodeEntity->get($iiifserver_manifest_viewing_direction_property)->value : '';
    }

    $manifest = [
      '@context' => 'http://iiif.io/api/presentation/2/context.json',
      '@id' => $prefix . 'manifest',
      '@type' => 'sc:Manifest',
      'label' => $title,
      // 'attribution' => $iiifserver_manifest_attribution_default,
      "seeAlso" => $this->createSeeAlso($nodeEntity, $prefix),
      'metadata' => $metadata,
      'sequences' => [
        $sequence
      ],
    ];

    $descriptionField = $config->get('description_field');
    $description = $nodeEntity->hasField($descriptionField) && !$nodeEntity->get($descriptionField)->isEmpty() ? $nodeEntity->get($descriptionField)->value : '';
    if ($description) {
      $manifest['description'] = $description;
    }

    $iiifserver_manifest_rights_text = $config->get('iiifserver_manifest_rights_text');
    $iiifserver_manifest_rights_property = $config->get('iiifserver_manifest_rights_property');

    if($iiifserver_manifest_rights_property) {
      $license = $this->getFieldValue($nodeEntity, $iiifserver_manifest_rights_property, 'uri');
      if ($license) {
        $manifest['license'] = $license;
      }
    } else if ($iiifserver_manifest_rights_text) {
      $manifest['license'] = $iiifserver_manifest_rights_text;
    }
    /*
    if ($iiifserver_manifest_rights_text) {
      $manifest['license'] = $iiifserver_manifest_rights_text;
    } else {
      
      $license = $this->getFieldValue($nodeEntity, $iiifserver_manifest_rights_property, 'uri');
      if ($license) {
        $manifest['license'] = $license;
      }
    }
    */

    $iiifserver_manifest_attribution_property = $config->get('iiifserver_manifest_attribution_property');

    if ($iiifserver_manifest_attribution_property) {
      $attribution = $this->getFieldValue($nodeEntity, $iiifserver_manifest_attribution_property, 'value');
      if ($attribution) {
        $manifest['attribution'] = $attribution;
      }
    } else {
      $manifest['attribution'] = $iiifserver_manifest_attribution_default;
    }

    return $manifest;
  }

  private function createSeeAlso(NodeInterface $nodeEntity, $prefix) {

    // ノードのUUIDを取得
    $uuid = $nodeEntity->uuid();
    
    // ノードのタイプを取得
    $nodeType = $nodeEntity->getType();

    $apiPrefix = explode('/api/iiif/', $prefix)[0];

    return [
      "@id" => $apiPrefix . "/jsonapi/node/" . $nodeType . "/" . $uuid,
      "format" => "application/vnd.api+json"
    ];
  }

  private function getCanvases(NodeInterface $nodeEntity, $iiifserverField, $prefix) {
    $canvases = [];
    if ($nodeEntity->hasField($iiifserverField) && !$nodeEntity->get($iiifserverField)->isEmpty()) {
      foreach ($nodeEntity->get($iiifserverField) as $index => $fieldItem) {
        $num = $index + 1;
        $imageEntity = $fieldItem->entity;
        if ($imageEntity) {
          $canvases[] = $this->buildCanvas($imageEntity, $prefix, $num);
        }
      }
    }
    return $canvases;
  }

  private function buildCanvas($imageEntity, $prefix, $num) {
    $uri = $imageEntity->get('title')->value;
    $serviceId = str_replace('/info.json', '', $uri);
    $imageUrl = str_replace('info.json', 'full/full/0/default.jpg', $uri);
    $thumbnailUrl = str_replace('info.json', 'full/!200,200/0/default.jpg', $uri);
    $width = intval($imageEntity->field_iiif_image_width->value);
    $height = intval($imageEntity->field_iiif_image_height->value);
    $canvasId = $prefix . 'canvas/p' . $num;

    return [
      '@id' => $canvasId,
      '@type' => 'sc:Canvas',
      'label' => '[' . $num . ']',
      'width' => $width,
      'height' => $height,
      'thumbnail' => [
        '@id' => $thumbnailUrl,
      ],
      'images' => [
        [
          '@id' => $canvasId . '/annotation',
          '@type' => 'oa:Annotation',
          'motivation' => 'sc:painting',
          'resource' => [
            '@id' => $imageUrl,
            '@type' => 'dctypes:Image',
            'format' => 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'service' => [
              '@context' => 'http://iiif.io/api/image/2/context.json',
              '@id' => $serviceId,
              'profile' => 'http://iiif.io/api/image/2/level2.json',
            ],
          ],
          'on' => $canvasId,
        ],
      ],
    ];
  }

  /**
   * ノードエンティティからメタデータを取得します。
   *
   * @param \Drupal\node\NodeInterface $nodeEntity ノードエンティティ
   * @return array メタデータの配列
   */
  private function getMetadata(NodeInterface $nodeEntity) {
    // ログインユーザーのアカウントを取得
    $current_user = \Drupal::currentUser();
    
    $nodeType = $nodeEntity->getType();
    $fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $nodeType);
    $metadata = [];
    $config = \Drupal::config('iiif_server.settings');
    $skipFields = [$config->get('iiifserver_manifest_rights_property')];

    foreach ($fieldDefinitions as $fieldName => $definition) {


      $field = $nodeEntity->get($fieldName);
        
      // フィールドのビュー権限をチェック
      if (!$field->access('view', $current_user)) {
        continue;
      }

      if ($this->isValidField($nodeEntity, $fieldName, $skipFields)) {
        $fieldType = $definition->getType();

        // フィールドタイプがテキストまたは数値の場合に処理
        if (in_array($fieldType, ['string', 'string_long', 'text', 'text_long', 'text_with_summary', 'integer', 'decimal', 'float'])) {
          $value = $this->getFieldValue($nodeEntity, $fieldName, "value");
          if (!empty($value)) {
          
          
            $metadata[] = [
                'label' => $definition->getLabel(),
                'value' => $value,
            ];
          }
        } else if(in_array($fieldType, ["link"])) {
          $value = $this->getFieldValue($nodeEntity, $fieldName, "uri");
          if (!empty($value)) {
            
          
            $metadata[] = [
                'label' => $definition->getLabel(),
                'value' => $value,
            ];
          }
        }
      }
    }

    return $metadata;
  }


  private function isValidField(NodeInterface $nodeEntity, $fieldName, $skipFields) {
    return strpos($fieldName, 'field_') === 0 && $nodeEntity->hasField($fieldName) && !in_array($fieldName, $skipFields);
  }

  private function getFieldValue(NodeInterface $nodeEntity, $fieldName, $fieldType = 'value') {
    if ($nodeEntity->hasField($fieldName) && !$nodeEntity->get($fieldName)->isEmpty()) {
      return $nodeEntity->get($fieldName)->{$fieldType};
    }
    return '';
  }
}