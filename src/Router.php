<?php
declare(strict_types=1);
namespace icePHP;

/**
 * 处理URL Rewrite 的类
 * 每一种需要特殊处理的URL地址格式,都需要在以下两个方法中实现,其中一个是从URL向Request转化,另一个是从Request向URL转化
 */
final class Router
{
    //禁止实例化
    private function __construct()
    {
    }

    //M,C,A参数名称
    static private $mcaName;

    //当前系统有哪些模块
    static private $modules;

    /**
     * 初始化,由SFrame调用
     * @param $mcaName array MCA参数名称
     * @param array $modules 当前系统有哪些模块
     */
    public static function init(array $mcaName, array $modules):void
    {
        self::$mcaName = $mcaName;
        self::$modules = $modules;
    }

    /**
     * 生成缺省的控制器及动作
     */
    static private function decodeDefault():void
    {
        // 取MCA三项参数名及默认值
        list ($m, $c, $a) = self::$mcaName;
        list ($mDefault, $cDefault, $aDefault) = self::getMCADefault();

        // 如果未提供模块名,则空
        if (!isset($_REQUEST[$m])) {
            $_GET[$m] = $_REQUEST[$m] = $mDefault;
        }

        // 如果请求中没有提供控制器,则使用默认的控制器
        if (!isset($_REQUEST[$c])) {
            $_GET[$c] = $_REQUEST[$c] = $cDefault;
        }

        // 如果请求中没有提供动作,则使用默认的动作
        if (!isset($_REQUEST[$a])) {
            $_GET[$a] = $_REQUEST[$a] = $aDefault;
        }
    }

    /**
     * 获取系统配置中的MAC缺少值
     * @return array [默认模块名称,默认控制器,默认动作]
     */
    static private function getMCADefault():array
    {
        $system = Config::get('system');
        return [
            $system['default_module'],
            $system['default_controller'],
            $system['default_action']
        ];
    }

    /**
     * 根据配置文件中的路由配置进行解析
     *
     * @param string $path
     * @return string|bool
     */
    static private function decodeConfig(string $path):?string
    {
        // 根据路由配置进行解码,循环每一个配置
        foreach (Config::get('router') as $key => $case) {
            // 下划线开头的表示配置参数,不是路由
            if (substr($key, 0, 1) == '_') {
                continue;
            }

            // 如果没有设置解码参数
            if (!isset($case['decode']) or !$case['decode']) {
                continue;
            }

            // 取路由配置中的解码参数: 源 正则 和 替换内容
            list ($regular, $replace) = $case['decode'];

            // 如果源 正则匹配,则表明需要使用此规则进行处理
            if (preg_match($regular, $path)) {
                // 如果匹配路由,返回替换后的路由
                return preg_replace($regular, $replace, $path);
            }
        }

        return null;
    }

    /**
     * 根据URI地址解析出控制器,动作及参数, 依据配置文件router
     *
     * @param string $path controller/action, 不包括 协议及域名,端口,参数列表
     */
    static public function decode(string $path = ""):void
    {
        // 是否特指解析
        $replace = self::decodeConfig($path);

        // 如果是特指解析
        if ($replace) {
            // 更换路径
            $path = $replace;

            // 特指解析下,必须按路径模式
            $mode = '路径模式';
        } else {
            // URL模式:传统模式/单入口模式/路径模式
            $mode = Config::get('system', 'url_mode');
        }

        // 如果路径为空,跳转到最后,补充默认控制器与方法
        // 或者不是路径模式
        if (!$path or $mode != '路径模式') {
            self::decodeDefault();
            return;
        }

        // 例 <path_root><m>/<c>/<a>/<k1>/<v1>/<k2>/<v2>/...
        // 去除前导index.php
        $path = preg_replace('/^(\w+\.php)/', '', $path);

        // 如果没有控制器和动作
        if (!$path) {
            self::decodeDefault();
            return;
        }

        // 路径分解
        $path = explode('/', $path);
        $count = count($path);

        // 如果没有
        if (!$count) {
            self::decodeDefault();
            return;
        }

        // 取当前所有可能的模块
        $modules = self::$modules;

        // 默认的模块,控制器,动作名称
        list ($module, $controller, $action) = self::getMCADefault();

        // 计算实际的MCA值
        // 如果第一个路径的值,与模式名匹配
        if ($modules and in_array($path[0], $modules)) {
            $module = array_shift($path);
        }

        // 下一个参数是C
        if (count($path)) {
            $controller = array_shift($path);
        }

        // 下一个参数是A
        if (count($path)) {
            $action = array_shift($path);
        }
        // 将解析出来的MCA重新赋值回请求参数中
        list ($m, $c, $a) = self::$mcaName;
        $_GET[$m] = $_REQUEST[$m] = $module;
        $_GET[$c] = $_REQUEST[$c] = $controller;
        $_GET[$a] = $_REQUEST[$a] = $action;

        // 如果有后继参数列表,解析
        while (count($path)) {
            $key = array_shift($path);
            $value = array_shift($path);
            $_GET[$key] = $_REQUEST[$key] = $value;
        }
    }

    /**
     * 处理 编码时的 匹配后处理
     * @param $pattern string 转义后的路径
     * @param array $params 参数
     * @return string 继续 转义后的路径
     */
    static private function encodeMatched(string $pattern, array $params):string
    {
        // 参数替换进去
        $others = [];
        foreach ($params as $k => $v) {
            if (strstr($pattern, '{' . $k . '}') !== false) {
                $pattern = str_replace('{' . $k . '}', $v, $pattern);
            } else {
                $others[$k] = $v;
            }
        }

        //剩下的参数附加上去
        $pattern = self::urlAppend($pattern, $others);

        return trim($pattern, '?');
    }

    /**
     * 为一个URL地址附加一些参数
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    static public function urlAppend(string $url, array $params):string
    {
        // 如果已经有参数,附加&,否则附加?
        if (!strpos($url, '?')) {
            $url .= "?";
        } else {
            $url .= "&";
        }

        // 逐个参数附加
        foreach ($params as $k => $v) {
            $url .= "$k=$v&";
        }

        // 去掉最后的&
        $url = trim($url, '&');

        // 返回新的URL
        return $url;
    }

    /**
     * 根据控制器名称,动作名称及参数构造URL
     * 使用配置文件router
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param string $action 动作名称
     * @param array $params 参数表
     * @return string 生成的URL地址
     */
    static public function encode(string $module = '', string $controller = "", string $action = "", array $params = []):string
    {
        // 配置中的根
        $url = Config::get('system', 'host');

        // 配置中的路由模式
        $mode = Config::get('system', 'url_mode');

        //取默认MCA
        list($defaultM, $defaultC, $defaultA) = self::getMCADefault();

        //如果未提供,则使用默认值
        $module = $module ?: $defaultM;
        $controller = $controller ?: $defaultC;
        $action = $action ?: $defaultA;

        // 根据路由配置进行编码
        foreach (Config::get('router') as $key => $case) {
            // 这个不是路由配置,而是参数
            if (substr($key, 0, 1) == '_') continue;

            // 如果没有编码这个配置项
            if (!isset($case['encode']) or !$case['encode']) continue;

            //如果编码是一个方法
            if (is_callable($case['encode'])) {
                //调用 回调方法进行转义
                $return = $case['encode']($module, $controller, $action, $params);

                //如果不符合规则,下一条
                if (!$return) continue;

                //处理的结果
                list($pattern, $params) = $return;
                $params = $params ?: [];

                //转义后处理
                return self::encodeMatched($pattern, $params);
            }

            // 取配置,前三项为模块名,控制器,动作名称
            $rule = $case['encode'];
            list ($m, $c, $a) = $rule;

            // 如果控制器与动作相同,使用此规则
            if ($m == $module and $c == $controller and $a == $action) {
                // 把控制器名称和动作名称替换进去
                $pattern = str_replace(['{m}', '{c}', '{a}'], [$module, $controller, $action], $rule[3]);

                //转义后处理
                return self::encodeMatched($pattern, $params);
            }
        }

        // 例 /<m>/<c>/<a>/<k1>/<v1>/<k2>/<v2>/...
        if ($mode == '路径模式') {
            if ($module) $url .= $module . '/';

            // 附加控制器名和动作名
            if ($controller) {
                $url .= $controller . '/';
                if ($action) $url .= $action . '/';
            }

            // 附加参数
            foreach ($params as $key => $value) {
                if (!is_null($value)) $url .= $key . '/' . urlencode($value) . '/';
            }
            return trim($url, '?');
        }

        if ($mode == '传统模式') {
            // 例: /index.php?<k1>=<v1>&<k2=v2>...
            $url .= 'index.php';
        }

        $url .= '?';

        // 取三项参数名
        list ($mName, $cName, $aName) = self::$mcaName;

        // 附加模块名称
        if ($module) $url .= $mName . '=' . $module . '&';

        // 附加控制器名称
        if ($controller) $url .= $cName . '=' . $controller . '&';

        // 附加动作名称
        if ($action) $url .= $aName . '=' . $action . '&';

        // 附加参数
        foreach ($params as $key => $value) {
            $url .= $key . '=' . urlencode(''.$value) . '&';
        }
        return trim(trim($url, '&'), '?');
    }

    /**
     * 判断指定路径是否是需要忽略解码的路径
     * 使用配置文件router中的_ignore配置数组
     *
     * @param string $path URI 去除了 协议,端口,站点,以首个/
     * @return boolean
     */
    static public function ignore(string $path):bool
    {
        // 取配置
        $all = Config::get('router', '_ignore');

        // 如果没有配置,或为空
        if (!$all) {
            return false;
        }

        // 逐个配置查看
        foreach ($all as $case) {
            // 是否正则匹配
            if (preg_match($case, $path)) {
                return true;
            }
        }

        // 没有能匹配的
        return false;
    }
}

