---
description: 代码风格指南，供 AI 模型在修改代码时遵循。
alwaysApply: true
---

# 编码规范

- 变量、函数尽量使用短英文名称(2-8个字符)
- 永远使用 tab 缩进
- 减少不必要的换行

## UI控件(css class,html id,winform等) 元素命名前缀

- `e` — 输入控件：`eport`, `ebaud`, `esend`, `esearch`, `enc`
- `b` — 按钮：`bopen`, `bsend`, `bclear`, `btoggle`
- `mn` — 菜单项：`mnnewwin`, `mnexit`, `mncomport`
- `lb` — 标签/统计显示：`lbcount`, `lbnsend`, `lbnrecv`, `lbrate`
- `p` — 面板容器：`pserial`, `ptcp`, `pside`
- `col` — 列定义：`colside`
- `sp` — 分隔条：`sph`, `spv`, `spside`

## css
1. 紧凑写法，一行一个选择器，多属性合并在一行，如 `* {margin:0;padding:0;}`
2. 类名规范：全小写，单词直接连接不使用连字符或下划线，如 `itemcard`, `pagelist`, `btns`
3. 工具类：简短缩写，如 `ml5`(margin-left:5px), `mt10`(margin-top:10px), `a00c`(color:#00c), `bf00`(color:#f00)
4. 颜色值：使用十六进制表示，如 `#2563eb`, `#f5f5f7`
5. 尽量使用 flex 布局，响应式设计

## JavaScript

1. 代码结构：使用 IIFE 立即执行函数包裹整个模块，如 `(function(){ ... })();`
2. 页面模式：使用 `returnJsPage(async function(home){ ... })` 返回页面对象
3. 变量命名：尽量使用短名称（2-8字符），如 `F`, `t`, `o`, `v`, `k`, `i`, `arr`, `obj`
4. 页面对象：统一用 `t` 表示当前页面对象，包含 `home`, `el`, `items` 等属性
5. 工具类：使用 `FClass` 实例 `F` 作为工具集，通过 `F.extend()` 合并对象
6. 字段定义：用逗号分隔的字符串定义字段列表，如 `SFIELDS = 'kw,fleet,isblack'`
7. 数组索引：用 `F.GetFArray(fields)` 生成字段名到数组索引的映射对象 `F`
8. 回调模式：使用 `F.cb_okfunc()`, `F.cb_func()`, `F.cb_alert()`, `F.cb_confirm()` 包装回调
9. 函数声明：使用 `function name(){}` 声明，大括号与函数名同行
10. 异步编程：使用 `async/await` + Promise，`F.get()`/`F.post()` 发起请求
11. 缩进：使用 tab 缩进
12. 字符串拼接：优先用模板字符串或 `+` 拼接
13. 管理端：基于 ExtJS 框架，使用 `F.makecombo_*` 系列创建下拉框，`canvasDatagrid` 做表格
14. 微信端：基于 React + 自定义 `F.h()` 创建 DOM，使用 `F.ReactWrapComponent()` 包装组件
15. 模块划分：用 `initxxx()` 函数包裹一组相关功能，返回 object，如 `initajax(){ return {get,post,ajax} }`，再通过 `extend(FClass.prototype, initxxx())` 扩展到原型
16. 局部函数：`initxxx()` 内部，不需要暴露的函数用 `function name(){}` 定义在 `return` 语句之后；需要暴露的方法直接挂在返回对象上
```js
function initlvcang(){
	var ds, lv = new Ext.grid.Panel({
		setEditable(en){ ... },    // 暴露方法
		setValue(arr){ ... },      // 暴露方法
		getValue(){ return ds.data.items.map(...); },
	});
	return lv;
	function add(){ ... }         // 局部函数，定义在 return 之后
	function onbtn(){ ... }       // 局部函数
}
```
17. CreatePage()：创建页面对象 `t`，包含 `doms:{}` 存储命名元素、`destroy()` 销毁方法
18. CreateDOM(page, o)：用对象配置创建 DOM，参数：`x` Emmet语法(`'tag.class#id'`)、`c` 子元素/类名、`s` 样式、`html` innerHTML、`name` 注册名、`onclick` 等事件
19. HTML模块封装：每个模块用 `initxxx()` 函数返回 `{dom, setValue, ...}`，`dom` 由 `F.CreateDOM()` 创建，暴露的方法挂在返回对象上
```js
function initqone(v,pos){
	var qone = {pos, v, opts:[], setValue(ans,ca,state){ ... }};
	qone.dom = F.CreateDOM(t, {x:'.li.qone', c:[
		{x:'.head', c:[{x:'.lbtype', c:pos+1+'.'}]},
		{x:'.opts', c:opts.map(o=>{x:'button.opt', onclick:onclick})},
	]});
	return qone;
}
function initdom(){
	t.main = F.CreateDOM(t, {x:'.page1', c:[
		{name:'lbtitle', x:'h1', c:'标题'},
		{name:'ul', c:[]},
		{name:'bsave', onclick:bsave_click, x:'button', c:'保存'},
	]});
}
// 访问: t.doms.lbtitle, t.doms.ul, t.doms.bsave
```

## html

2. CSS 变量：使用 `:root { --bg: #xxx; --accent: #xxx; }` 定义主题变量
3. 图标：使用 Font Awesome 图标类（`fa-*`）
5. 属性引号：使用双引号包裹 HTML 属性值
6. 页面结构：管理端用 ExtJS 渲染，微信端用原生 HTML + React