<?php

namespace Drupal\iiif_server\View\Helper;

use Drupal\node\NodeInterface;

class IiifHelper {

    private function getFieldValue(NodeInterface $nodeEntity, $fieldName, $fieldType = 'value') {
        if ($nodeEntity->hasField($fieldName) && !$nodeEntity->get($fieldName)->isEmpty()) {
          return $nodeEntity->get($fieldName)->{$fieldType};
        }
        return '';
      }

    public function getLicenseUri($nodeEntity) {
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

    public function getAttribution($nodeEntity) {
        $config = \Drupal::config('iiif_server.settings');

        $attributionLabel = "";

        $iiifserver_manifest_attribution_property = $config->get('iiifserver_manifest_attribution_property');

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

          if($attributionLabel == "") {
            return null;
          }

          $attributions =  [
            $attributionLabel
        ];

        $rs = $this->getFieldValue($nodeEntity, "field_rs", 'value');

        if($rs) {
            $attributions[] = $rs;
        }

        return $attributions;
    }

    public function createSeeAlso3($nodeEntity, $prefix) {
      // ノードのUUIDを取得
      $uuid = $nodeEntity->uuid();

      // ノードのタイプを取得
      $nodeType = $nodeEntity->getType();

      $apiPrefix = explode('/api/iiif/', $prefix)[0];

      return [
        "id" => $apiPrefix . "/jsonapi/node/" . $nodeType . "/" . $uuid,
        "type" => "Dataset",
        "label" => [
            "none" => [
                "Api rest json"
            ]
        ],
        "format" => "application/vnd.api+json"
      ];
    }
}