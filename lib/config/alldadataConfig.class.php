<?php
/**
 * Application config class
 *
 * @license MIT
 */
 
 class alldadataConfig extends waAppConfig
 {
    /**
     * Returns new API instance
     */
    public function getApiClient()
    {
        return new alldadataApi();
    }
 }
