<?php

namespace Drupal\iiif_server\View\Helper;

use Drupal\node\NodeInterface;

class IiifGeoManifest3 {

    public function buildManifestVersion3(NodeInterface $nodeEntity, $prefix) {

        $config = \Drupal::config('iiif_server.settings');

        $iiifserver_field = $config->get("iiifserver_field");

        $canvases = $this->createCanvases($nodeEntity, $prefix, $iiifserver_field);

        
        

        $manifest = [
            
            '@context' => [
                'http://iiif.io/api/presentation/3/context.json',
                "http://iiif.io/api/extension/georef/1/context.json"
            ],
            'id' => $prefix . 'manifest',
            'type' => 'Manifest',
            'label' => [
                'none' => [
                    $nodeEntity->getTitle()
                ]
            ],
            
                
        ];

        $helper = new IiifHelper();

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
                            // 'motivation' => 'georeferencing',
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
            $field_geo = $node->get("field_geo"); // ->value;

            if ($field_geo === null) {
                continue;
            }

            
            if ($field_geo->lon === null) {
                continue;
            }

            $annotations[] = [
                "field_id" => $node->get("field_id")->value,
                "title" => $title,
                "field_xywh" => $xywh,
                "field_body" => $body,
                "field_geo" => [
                    $field_geo->lon,
                    $field_geo->lat
                ]
            ];
        }

        $items = [];
        
        foreach ($annotations as $index => $anno) {
            // xywh の文字列をカンマで分割して x, y, w, h を取得
            list($x, $y, $width, $height) = explode(',', $anno["field_xywh"]);

            // 中心座標を計算
            $centerX = $x + ($width / 2);
            $centerY = $y + ($height / 2);

            $items[] = 
                [
                    "type" => "Feature",
                    "metadata" => [
                        "xywh" => $anno["field_xywh"],
                        "label" => $anno["title"],
                        "id" => $anno["field_id"],
                    ],
                    "geometry" => [
                        "coordinates" => $anno["field_geo"],
                        "type" => "Point"
                    ],
                    "properties" => [
                        "resourceCoords" => [
                            $centerX, $centerY
                        ]
                    ]
                ];
        }
                
        
        return [
            [
                "id" => $canvasId . "/annos",
                "type" => "AnnotationPage",
                "items" => [
                    [
                        "id" => $canvasId . "/annos/geo",
                        "type" => "Annotation",
                        "motivation" => "georeferencing",
                        
                        "target" => $canvasId,
                        "body" => [
                            "features" => $items,
                            "id" => $canvasId . "/annos/geo/features",
                            "transformation" => [
                                "options" => [
                                    "order" => 1
                                ],
                                "type" => "polynomial"
                            ],
                            "type" => "FeatureCollection"
                        ]
                    ]
                ]
            ]
        ];
    }
}