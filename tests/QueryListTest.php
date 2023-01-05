<?php

namespace Jiannei\LaravelCrawler\Tests;

use Jiannei\LaravelCrawler\QueryList;

class QueryListTest extends TestCase
{
    private $html = <<<STR
<div id="one">
    <div class="two">
        <a href="http://querylist.cc">QueryList官网</a>
        <img src="http://querylist.com/1.jpg" alt="这是图片" abc="这是一个自定义属性">
        <img class="second_pic" src="http://querylist.com/2.jpg" alt="这是图片2">
        <a href="http://doc.querylist.cc">QueryList文档</a>
    </div>
    <span>其它的<b>一些</b>文本</span>
</div>
STR;

    public function testFindElementAttr()
    {
        $ql = QueryList::html($this->html);

        // 获取第一张图片的属性
        $attr1 = $ql->find('img')->attr('src');
        $attr2 = $ql->find('img')->src;
        $attr3 = $ql->find('img:eq(0)')->src;
        $attr4 = $ql->find('img')->eq(0)->src;
        $attr5 = $ql->find('img')->eq(0)->attr('src');

        $this->assertEquals('http://querylist.com/1.jpg', $attr1);
        $this->assertEquals($attr1, $attr2);
        $this->assertEquals($attr1, $attr3);
        $this->assertEquals($attr1, $attr4);
        $this->assertEquals($attr1, $attr5);

        // 获取第一张图片的alt属性
        $alt = $ql->find('img')->alt;
        $this->assertEquals('这是图片', $alt);

        //获取第一张图片的abc属性，注意这里获取定义属性的写法与普通属性的写法是一样的
        $abc = $ql->find('img')->abc;
        $this->assertEquals('这是一个自定义属性', $abc);

        //获取第二张图片的alt属性
        $alt1 = $ql->find('img')->eq(1)->alt;
        //等价下面这句话
        $alt2 = $ql->find('img:eq(1)')->alt;
        //也等价下面这句话，通过class选择图片
        $alt3 = $ql->find('.second_pic')->alt;

        $this->assertEquals('这是图片2', $alt1);
        $this->assertEquals($alt1, $alt2);
        $this->assertEquals($alt1, $alt3);
    }

    public function testFindElementAllAttrs()
    {
        $ql = QueryList::html($this->html);

        // 获取元素的所有属性
        $attrs1 = $ql->find('img:eq(0)')->attr('*');

        $this->assertIsArray($attrs1);
        $this->assertEquals([
            "src" => "http://querylist.com/1.jpg",
            "alt" => "这是图片",
            "abc" => "这是一个自定义属性",
        ], $attrs1);

        $attrs2 = $ql->find('a:eq(1)')->attr('*');
        $this->assertEquals([
            "href" => "http://doc.querylist.cc",
        ], $attrs2);
    }

    public function testGetElementHtmlOrText()
    {
        // 获取元素内的html内容或text内容
        $ql = QueryList::html($this->html);

        // 获取元素下的HTML内容
        $html = $ql->find('#one>.two')->html();
        $this->assertEquals(
            <<<STR
<a href="http://querylist.cc">QueryList官网</a>
        <img src="http://querylist.com/1.jpg" alt="这是图片" abc="这是一个自定义属性">
        <img class="second_pic" src="http://querylist.com/2.jpg" alt="这是图片2">
        <a href="http://doc.querylist.cc">QueryList文档</a>
STR,
            $html
        );

        // 获取元素下的text内容
        $text = $ql->find('.two')->text();

        $this->assertEquals('ok','ok');
    }

    public function testGetElementsAttr()
    {
        // 获取多个元素的单个属性
        $ql = QueryList::html($this->html);

        // 获取class为two的元素下的所有图片的alt属性
        $alts1 = $ql->find('.two img')->map(function ($item) {
            return $item->alt;
        });

        // 等价下面这句话
        $alts2 = $ql->find('.two img')->attrs('alt');

        $this->assertEquals(["这是图片", "这是图片2"], $alts1->all());
        $this->assertEquals($alts1, $alts2);

        // 获取选中元素的所有html内容和text内容
        $texts = $ql->find('.two>a')->texts();
        $this->assertEquals(["QueryList官网", "QueryList文档"], $texts->all());

        $htmls = $ql->find('#one span')->htmls();
        $this->assertEquals(["其它的<b>一些</b>文本"], $htmls->all());
    }

    public function testGetHtmlByUrl()
    {
        $ql = QueryList::get('https://www.ithome.com/html/discovery/358585.htm');

        $rt = [];
        // DOM解析文章标题
        $rt['title'] = $ql->find('h1')->text();
        // DOM解析文章作者
        $rt['author'] = $ql->find('#author_baidu>strong')->text();
        // DOM解析文章内容
        $rt['content'] = $ql->find('.post_content')->html();

        $this->assertEquals('ok','ok');
    }

    public function testRules()
    {
        $url = 'https://www.ithome.com/html/discovery/358585.htm';

        $ql = QueryList::get($url);
        $rt1 = [];
        // DOM解析文章标题
        $rt1['title'] = $ql->find('h1')->text();
        // DOM解析文章作者
        $rt1['author'] = $ql->find('#author_baidu>strong')->text();
        // DOM解析文章内容
        $rt1['content'] = $ql->find('.post_content')->html();

        // 定义DOM解析规则
        $rules = [
            // DOM解析文章标题
            'title' => ['h1', 'text'],
            // DOM解析文章作者
            'author' => ['#author_baidu>strong', 'text'],
            // DOM解析文章内容
            'content' => ['.post_content', 'html'],
        ];
        $rt2 = QueryList::get($url)->rules($rules)->query()->getData();
        $this->assertEquals($rt1, $rt2->all());

        $rt3 = QueryList::get($url)->rules($rules)->queryData();
        $this->assertEquals($rt1, $rt3);
    }

    public function testRange()
    {
        $url = 'https://it.ithome.com';
        // 元数据DOM解析规则
        $rules = [
            // DOM解析文章标题
            'title' => ['h2>a', 'text'],
            // DOM解析链接
            'link' => ['h2>a', 'href'],
            // DOM解析缩略图
            'img' => ['.list_thumbnail>img', 'data-original'],
            // DOM解析文档简介
            'desc' => ['.memo', 'text'],
        ];

        // 切片选择器
        $range = '.content li';
        $rt = QueryList::get($url)->rules($rules)
            ->range($range)->query()->getData();

        $this->assertEquals('ok','ok');
    }

    public function testSingleElementContentFilter()
    {
        $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

        // DOM解析正文内容
        $ql = QueryList::html($html)->find('#content');
        // 选择正文内容中要移除的元素，并移除
        $ql->find('.tt,span:last,p:last')->remove();
        // 获取纯净的正文内容
        $content = $ql->html();

        $this->assertEquals(
            <<<STR
这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>
STR,
            $content
        );

        $this->assertEquals('ok','ok');
    }

    public function testMultiElementsContentFilterByRules()
    {
        $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

        // DOM解析规则
        $rules = [
            //设置了内容过滤选择器
            'content' => ['#content', 'html', '-.tt -span:last -p:last'],
        ];

        $rt = QueryList::rules($rules)->html($html)->query()->getData();

        $this->assertEquals([
            'content' => <<<STR
这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>
STR
            ,
        ], $rt->all());

        $this->assertEquals('ok','ok');
    }


    public function testMultiElementsContentFilterByRules2()
    {
        $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <a href="http://querylist.cc">QueryList官网</a>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

        // html 只保留内容不保留标签，保留其他标签
        // text 保留标签且保留内容，移除其他标签
        // 差集？
        $rules = [
            // 移除内容中所有的超链接，但保留超链接的内容，并移除内容中所有p标签，但保留p标签的内容
            'content_html' => ['#content', 'html', 'a p'],
            // 保留内容中的超链接，以及保留p标签及内容
            'content_text' => ['#content', 'text', 'a p'],
        ];

        $rt = QueryList::rules($rules)->html($html)->query()->getData();

        $this->assertEquals('ok','ok');
    }

    public function testMultiElementsContentFilterByRemove()
    {
        $html = <<<STR
    <div id="content">

        <span class="tt">作者：xxx</span>

        这是正文内容段落1.....

        <span>这是正文内容段落2</span>

        <p>这是正文内容段落3......</p>

        <span>这是广告</span>
        <p>这是版权声明！</p>
    </div>
STR;

        $rules = [
            'content' => ['#content', 'html'],
        ];

        $rt = QueryList::rules($rules)
            ->html($html)
            ->query()
            ->getData(function ($item) {
                $ql = QueryList::html($item['content']);
                $ql->find('.tt,span:last,p:last')->remove();
                $item['content'] = $ql->find('')->html();

                return $item;
            });

        $this->assertEquals('ok','ok');
    }

    public function testEncoding()
    {
        $html = <<<STR
<div>
    <p>这是内容</p>
</div>
STR;
        $rule = [
            'content' => ['div>p:last', 'text'],
        ];
        $data = QueryList::html($html)->rules($rule)->encoding('UTF-8', 'GB2312')->query()->getData();

        $this->assertEquals('ok','ok');
    }

    public function testRemoveHead()
    {
        $html = <<<STR
<div>
    <p>这是内容</p>
</div>
STR;
        $rule = [
            'content' => ['div>p:last', 'text'],
        ];
        $data = QueryList::html($html)->rules($rule)
            ->removeHead()->query()->getData();

        // 或者
        $data = QueryList::html($html)->rules($rule)
            ->encoding('UTF-8', 'GB2312')->removeHead()->query()->getData();

        $this->assertEquals('ok','ok');
    }

    public function testParseResult()
    {
        $html = <<<STR
    <div class="xx">
        <img data-src="/path/to/1.jpg" alt="">
    </div>
    <div class="xx">
        <img data-src="/path/to/2.jpg" alt="">
    </div>
    <div class="xx">
        <img data-src="/path/to/3.jpg" alt="">
    </div>
STR;

        $result = QueryList::html($html)->rules([
            'image' => ['img', 'data-src'],
        ])->range('.xx')->query()->getData(function ($item) {
            return $item;
        });

        $this->assertEquals([["image" => "/path/to/1.jpg"], ["image" => "/path/to/2.jpg"], ["image" => "/path/to/3.jpg"]], $result->all());
        $this->assertEquals(["/path/to/1.jpg", "/path/to/2.jpg", "/path/to/3.jpg"], $result->flatten()->all());
        $this->assertEquals([["image" => "/path/to/1.jpg"], ["image" => "/path/to/2.jpg"]], $result->take(2)->all());
        $this->assertEquals([1 => ["image" => "/path/to/2.jpg"], 2 => ["image" => "/path/to/3.jpg"]], $result->take(-2)->all());
        $this->assertEquals([2 => ["image" => "/path/to/3.jpg"], 1 => ["image" => "/path/to/2.jpg"], 0 => ["image" => "/path/to/1.jpg"]], $result->reverse()->all());

        $filteredResult = $result->filter(function($item){
            return $item['image'] != '/path/to/2.jpg';
        })->all();
        $this->assertEquals([0 => ["image" => "/path/to/1.jpg"], 2 => ["image" => "/path/to/3.jpg"]], $filteredResult);
    }

    public function testReplaceAttrValue()
    {
        $html =<<<STR
    <div>
     <a href="https://querylist.cc" alt="abc">QueryList</a>
    </div>
STR;

        $ql = QueryList::html($html);
        // 获取a元素对象
        $link = $ql->find('a:eq(0)');

        // 设置元素属性值
        $link->attr('href','https://baidu.com');
        $link->attr('alt','百度');

        // 设置元素内容
        $link->text('百度一下');

        $this->assertEquals('<a href="https://baidu.com" alt="百度">百度一下</a>',$ql->find('div')->html());

        $link->html('<p>百度一下</p>');
        $this->assertEquals('<a href="https://baidu.com" alt="百度"><p>百度一下</p></a>',$ql->find('div')->html());
    }

    public function testAppendElement()
    {
        $html =<<<STR
    <div>
     <a href="https://querylist.cc" alt="abc">QueryList</a>
    </div>
STR;

        $ql = QueryList::html($html);
        // 获取div元素对象
        $div = $ql->find('div:eq(0)');
        // 向div元素中追加一个img元素
        $div->append('<img src="1.jpg" />');

        $rt = [];
        $rt[] = $div->find('img')->attr('src');
        $rt[]= $ql->find('div')->html();

        $this->assertEquals('ok','ok');
    }

    public function testReplaceElement()
    {
        $html =<<<STR
    <div>
     <a  href="https://qq.com">QQ</a>
     <a class="ql" href="https://querylist.cc" alt="abc">QueryList</a>
     <a  href="https://baidu.com">百度一下</a>
    </div>
STR;

        $ql = QueryList::html($html);

        $ql->find('a')->map(function($a){
            $text = $a->text();
            $a->replaceWith('<span>'.$text.'</span>');
        });

        $rt = $ql->find('div')->html();

        $this->assertEquals('ok','ok');
    }

    public function testRemoveAttr()
    {
        $html =<<<STR
    <div>
     <a  href="https://qq.com" alt="123">QQ</a>
     <a class="ql" href="https://querylist.cc" alt="abc">QueryList</a>
     <a  href="https://baidu.com">百度一下</a>
    </div>
STR;

        $ql = QueryList::html($html);

        $ql->find('a')->removeAttr('alt');

        $rt = $ql->find('div')->html();

        $this->assertEquals('ok','ok');
    }

    public function testGetElement()
    {
        $html =<<<STR
    <div>
     <a  href="https://qq.com">QQ</a>
     <a class="ql" href="https://querylist.cc" alt="abc">QueryList</a>
     <a  href="https://baidu.com">百度一下</a>
    </div>
STR;

        $ql = QueryList::html($html);
        // 获取class为 ql 的元素对象
        $link = $ql->find('.ql');

        $rt = [];
        // 获取父元素的内容
        $rt['parent'] = $link->parent()->html();
        // 获取临近的下一个元素的内容
        $rt['next'] = $link->next()->text();
        // 获取临近的前一个元素的属性
        $rt['prev'] = $link->prev()->attr('href');

        $this->assertEquals('ok','ok');
    }
}