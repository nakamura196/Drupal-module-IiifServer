<?php

namespace Drupal\iiif_server\View\Helper;

use Drupal\node\NodeInterface;

class IiifManifest3 {

    private function getFieldValue(NodeInterface $nodeEntity, $fieldName, $fieldType = 'value') {
        if ($nodeEntity->hasField($fieldName) && !$nodeEntity->get($fieldName)->isEmpty()) {
          return $nodeEntity->get($fieldName)->{$fieldType};
        }
        return '';
      }

    private function getLicenseUri($nodeEntity) {
        $config = \Drupal::config('iiif_server.settings');
        $licenseUri = "";
        $iiifserver_manifest_rights_text = $config->get('iiifserver_manifest_rights_text');
        $iiifserver_manifest_rights_property = $config->get('iiifserver_manifest_rights_property');

        if($iiifserver_manifest_rights_property) {
            $license = $this->getFieldValue($nodeEntity,  $iiifserver_manifest_rights_property, 'uri');
            if ($license) {
                // $manifest['license'] = $license;
                $licenseUri = $license;
            }
        }
        
        if (!$licenseUri && $iiifserver_manifest_rights_text) {
            // $manifest['license'] = $iiifserver_manifest_rights_text;
            $licenseUri = $iiifserver_manifest_rights_text;
        }

        return $licenseUri;
    }

    private function getAttribution($nodeEntity) {
        $config = \Drupal::config('iiif_server.settings');

        $attributionLabel = "";

        $iiifserver_manifest_attribution_default = $config->get('iiifserver_manifest_attribution_default');

        if ($iiifserver_manifest_attribution_property) {
            $attribution = $this->getFieldValue($nodeEntity, $iiifserver_manifest_attribution_property, 'value');
            if ($attribution) {
              // $manifest['attribution'] = $attribution;
            $attributionLabel = $attribution;
            }
          } /*  else {
            $manifest['attribution'] = $iiifserver_manifest_attribution_default;
          } */

          if (!$attributionLabel && $iiifserver_manifest_attribution_default) {
            // $manifest['attribution'] = $iiifserver_manifest_attribution_default;
            $attributionLabel = $iiifserver_manifest_attribution_default;
          }

            return $attributionLabel;
    }

    public function buildManifestVersion3(NodeInterface $nodeEntity, $prefix) {

        $config = \Drupal::config('iiif_server.settings');

        $iiifserver_field = $config->get("iiifserver_field");

        $canvases = $this->createCanvases($nodeEntity, $prefix, $iiifserver_field);

        
        

        $manifest = [
            
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $prefix . 'manifest',
            'type' => 'Manifest',
            'label' => [
                'none' => [
                    $nodeEntity->getTitle()
                ]
            ],
            
                
        ];

        $licenseUri = $this->getLicenseUri($nodeEntity);
        if ($licenseUri) {
            $manifest['rights'] = $licenseUri;
        }

        $attribution = $this->getAttribution($nodeEntity);

        if ($attribution) {
            $manifest['requiredStatement'] = [
                "label" => [
                    "none" => [
                        "Attribution"
                    ]
                ],
                "value" => [
                    "none" => [
                        $attribution
                    ]
                ]
                    ];
        }

        $manifest["items"] = $canvases;

        return $manifest;
    }

    public function createCanvases(NodeInterface $nodeEntity, $prefix, $iiifserverField) {
        $images = [];
        if ($nodeEntity->hasField($iiifserverField) && !$nodeEntity->get($iiifserverField)->isEmpty()) {
            foreach ($nodeEntity->get($iiifserverField) as $index => $fieldItem) {
                $num = $index + 1;
                $imageEntity = $fieldItem->entity;
                if ($imageEntity) {
                    $images[] = $this->buildCanvas($imageEntity, $prefix, $num);
                }
            }
        }
        
        return $images;
    }

    public function buildCanvas($imageEntity, $prefix, $num) {
        $uri = $imageEntity->get('title')->value;

        $serviceId = str_replace('/info.json', '', $uri);
        $imageUrl = str_replace('info.json', 'full/full/0/default.jpg', $uri);
        $thumbnailUrl = str_replace('info.json', 'full/200,/0/default.jpg', $uri);
        $width = intval($imageEntity->field_iiif_image_width->value);
        $height = intval($imageEntity->field_iiif_image_height->value);
        $canvasId = $prefix . 'canvas/p' . $num;
        $label = '[' . $num . ']';

        return [
                'id' => $canvasId, // $prefix . 'canvas',
                'type' => 'Canvas',
                "width" => $width,
                "height" => $height,
                "label" => [
                    "none" => [
                        $label
                    ]
                ],
                "thumbnail" => [
                    [
                        "format" => "image/jpeg",
                        "id" => $thumbnailUrl,
                        "type" => "Image"
                    ]
                ],
                "annotations" => $this->buildAnnotations($imageEntity, $canvasId),
                'items' => [
                    [
                        'id' => $canvasId . "/page",
                        'type' => 'AnnotationPage',
                        'items' => [[
                            'id' =>  $canvasId . "/page/imageanno",
                            'type' => 'Annotation',
                            'motivation' => 'painting',
                            'body' => [
                                'id' => $prefix . 'image',
                                'type' => 'Image',
                                'format' => 'image/jpeg',
                                'service' => [[
                                    'id' => $serviceId,
                                    'type' => 'ImageService2',
                                    'profile' => 'level2',
                                ]],
                                'width' => $width,
                                'height' => $height
                            ],
                            'target' => $canvasId
                        ]
                    ]
                ]
            ]
        ];
    }

    public function buildAnnotations($imageEntity, $canvasId) {

        $config = \Drupal::config('iiif_server.settings');

        $iiifserver_field = $config->get("iiifserver_field");

        // imageEntityのIDを取得
        $imageEntityId = $imageEntity->id();

        // 対象のimageEntityを持つannotationノードを取得
        $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
        $nids = $query
            ->condition('type', 'annotation')
            ->condition('status', 1) // 公開されたノードのみ
            ->condition($iiifserver_field, $imageEntityId)
            ->accessCheck(TRUE) // ここでアクセスチェックを有効にする
            ->execute();

        // ノードをロード
        $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

        $annotations = [];

        // 各ノードに対してアノテーションを作成
        foreach ($nodes as $node) {
            // ノードのタイトルや他のフィールドを使用してアノテーションを生成
            $title = $node->getTitle();
            $xywh = $node->get('field_xywh')->value; // 例: 0,0,500,500
            $body = $node->get('body')->value; // アノテーションの内容

            $annotations[] = [
                "title" => $title,
                "field_xywh" => $xywh,
                "field_body" => $body
            ];
        }

        $items = [];

        
        foreach ($annotations as $index => $anno) {
            $items[] = 
                [
                    "id" => $canvasId . "/annos/" . ($index + 1),
                    "type" => "Annotation",
                    "motivation" => "commenting",
                    "target" => $canvasId . "#xywh=" . $anno["field_xywh"],
                    "body" => [
                        "type" => "TextualBody",
                        "value" => $anno["field_body"]
                    ]
                ];
        }
                
        
        return [
            [
                "id" => $canvasId . "/annos",
                "type" => "AnnotationPage",
                "items" => $items
            ]
        ];
    }
}