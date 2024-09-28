<?php

namespace Drupal\iiif_server\View\Helper;

use Drupal\node\NodeInterface;
use Drupal\iiif_server\View\Helper\IiifHelper;

class IiifCollection3 {

    public function buildCollectionVersion3(NodeInterface $nodeEntity, $prefix, $node) {

        $config = \Drupal::config('iiif_server.settings');

        $iiifserver_field = $config->get("iiifserver_field");

        // $canvases = $this->createCanvases($nodeEntity, $prefix, $iiifserver_field);

        $parentId = $nodeEntity->id();

        // 対象のimageEntityを持つannotationノードを取得
        $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
        $nids = $query
            ->condition('status', 1) // 公開されたノードのみ
            ->condition("field_parent", $parentId)
            ->accessCheck(TRUE) // ここでアクセスチェックを有効にする
            ->execute();

        // ノードをロード
        $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

        $items = [];

        // 各ノードに対してアノテーションを作成
        foreach ($nodes as $child) {
        
            $title = $child->getTitle();

            $nodeType = $child->getType();

            $type = $nodeType === "collection" ? "Collection" : "Manifest";

            $itemId = "";

            if($type === "Manifest") {
                $field_id = $child->field_id->value;
                if(!$field_id) {
                    $field_id = $child->uuid();
                }
                $itemId = $prefix . "/" . $field_id . "/manifest";
            }

            $item = [
                "id" => $itemId,
                "type" => $type,
                'label' => [
                    'none' => [
                        $title
                    ]
                ],
            ];

            $items[] = $item;
        
        }


        $collection = [
            
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $prefix . '/collection/' . $node,
            'type' => 'Collection',
            'label' => [
                'none' => [
                    $nodeEntity->getTitle()
                ]
            ],
            "items" => $items
            
                
        ];

        return $collection;
    }
}