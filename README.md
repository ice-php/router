路由管理
=

* 初始化

    Router::init(array $mcaName, array $modules):void

    记录当前的MVC参数名称，通常是[m,v,c],以及全部Module名称。

* 解析 

    Router::decode(string $path = ""):void    
    
    依据配置文件router对URI地址进行解析，分析得到模块名称，控制器，动作及参数。 

* 构造地址

    Router::encode(string $module = '', string $controller = "", string $action = "", array $params = []):string
    
    或
    
    url(?string $module = '', ?string $controller = "", ?string $action = "", ?array $params = []): string
    
    根据模块名称，控制器名称，动作名称及参数构造URL。

* 为一个URL地址附加一些参数

    Router::urlAppend(string $url, array $params):string
    
    或
    
    urlAppend(string $url, array $params): string

* 判断指定路径是否是需要忽略解码的路径

    Router::ignore(string $path):bool