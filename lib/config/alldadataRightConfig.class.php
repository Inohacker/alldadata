<?php

class alldadataRightConfig extends waRightConfig
{
    public function init() {
        $this->addItem('settings', 'Доступ к настройкам', 'checkbox');
    }
}