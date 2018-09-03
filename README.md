# router
路由管理

## 初始化
init(array $mcaName, array $modules):void
记录当前的MVC参数名称,通常是[m,v,c],以及全部Module名称

## 根据URI地址解析出控制器,动作及参数, 依据配置文件router
decode(string $path = ""):void

## 根据控制器名称,动作名称及参数构造URL
encode(string $module = '', string $controller = "", string $action = "", array $params = []):string

## 为一个URL地址附加一些参数
urlAppend(string $url, array $params):string

## 判断指定路径是否是需要忽略解码的路径
ignore(string $path):bool