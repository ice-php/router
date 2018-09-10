<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 根据控制器名称,动作名称及参数构造URL
 * 这是Router的一个快捷入口
 *
 * @param string $module 模块名称
 * @param string $controller 控制器名称
 * @param string $action 动作名称
 * @param array $params 参数表
 * @return string 路径字符串,不带域名部分
 */
function url(string $module = '', string $controller = "", string $action = "", array $params = []): string
{
    return Router::encode($module, $controller, $action, $params);
}

/**
 * 为一个URL地址附加一些参数
 *
 * @param string $url
 * @param array $params
 * @return string
 */
function urlAppend(string $url, array $params): string
{
    return Router::urlAppend($url, $params);
}
