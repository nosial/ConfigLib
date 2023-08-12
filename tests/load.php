<?php

    require 'ncc';
    import('net.nosial.configlib');

    $config = new \ConfigLib\Configuration('test');

    var_dump($config->getConfiguration());