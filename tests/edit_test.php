<?php

    require 'ncc';
    import('net.nosial.configlib');

    $config = new \ConfigLib\Configuration('test');

    $config->set('database.host', '192.168.1.1');
    $config->set('database.username', 'super_root');

    $config->save();