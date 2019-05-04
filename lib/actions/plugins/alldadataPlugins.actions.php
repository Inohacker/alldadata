<?php

class alldadataPluginsActions extends waPluginsActions
{
    protected $plugins_hash = '#';
    protected $is_ajax = false;
    protected $shadowed = true;

    public function defaultAction()
    {
        if (!$this->getUser()->isAdmin($this->getApp())) {
            throw new waRightsException('Доступ ограничен');
        }

        $this->setLayout(new alldadataBackendLayout());
        parent::defaultAction();
    }
}