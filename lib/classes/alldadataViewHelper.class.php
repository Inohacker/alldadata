<?php

class alldadataViewHelper extends waAppViewHelper
{
    public function isBot($userAgent = null) {
        $dadata = new alldadataApi();
        return $dadata->isBot($userAgent);
    }

    public function ipGeoLocation($ip = '', $session = false, $returnPath = 'all') {
        $dadata = new alldadataApi();
        return $dadata->geoLocation($ip, $session, $returnPath);
    }

    public function kladrFiasAddress($query, $returnPath = 'all') {
        $dadata = new alldadataApi();
        return $dadata->kladrFiasAddress($query, $returnPath);
    }
}