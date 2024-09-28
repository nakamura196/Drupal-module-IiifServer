<?php

namespace Drupal\iiif_server\View\Helper;

use Drupal\node\NodeInterface;
use Drupal\iiif_server\View\Helper\IiifHelper;

class IiifManifest3 {

    

    

    public function buildManifestVersion3(NodeInterface $nodeEntity, $prefix) {

        $config = \Drupal::config('iiif_server.settings');

        $iiifserver_field = $config->get("iiifserver_field");

        $canvases = $this->createCanvases($nodeEntity, $prefix, $iiifserver_field);

        $helper = new IiifHelper();

        $manifest = [
            
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $prefix . 'manifest',
            'type' => 'Manifest',
            'label' => [
                'none' => [
                    $nodeEntity->getTitle()
                ]
            ],
            "seeAlso" => $helper->createSeeAlso3($nodeEntity, $prefix)
                
        ];

        

        $licenseUri = $helper->getLicenseUri($nodeEntity);
        if ($licenseUri) {
            $manifest['rights'] = $licenseUri;
        }

        $attributions = $helper->getAttribution($nodeEntity);

        if ($attributions) {
            $manifest['requiredStatement'] = [
                "label" => [
                    "none" => [
                        "Attribution"
                    ]
                ],
                "value" => [
                    "none" => $attributions
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

            $commonId = $node->get('field_jk')->value;

            $annotations[] = [
                "title" => $title,
                "field_xywh" => $xywh,
                "field_body" => $body,
                "field_commonId" => $commonId,
            ];
        }

        $items = [];

        
        foreach ($annotations as $index => $anno) {
            $item = [
                "id" => $canvasId . "/annos/" . ($index + 1),
                "type" => "Annotation",
                "motivation" => "commenting",
                "target" => $canvasId . "#xywh=" . $anno["field_xywh"],
                "body" => [
                    "type" => "TextualBody",
                    "value" => isset($anno["field_body"]) && $anno["field_body"] !== null ? $anno["field_body"] : $anno["title"]
                ]
            ];

            if($anno["field_commonId"] != null) {
                # $item["id"] = $anno["field_commonId"];
                $item["_compare"] = [
                    "id" => $anno["field_commonId"],
                    "label" => $anno["title"]
                ];
                $item["cid"] = $anno["field_commonId"];
            }

            $items[] = $item;
                
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