# article-image

WordPress 文章配图按名跳转接口。

打开 `article_image.php?res=图片名` → `302` 跳到对应的 `.webp`。

Named WordPress article-image redirect API.

## 用法 Usage

```text
https://你的站点/wp-content/removeable/article_image.php?res=cover-01
→ 302 Location: https://cjsy.cc/wp-content/removeable/article_images/cover-01.webp
```

| 参数 | 说明 |
|------|------|
| `res` | 图片名（不含扩展名）。默认允许 `A-Za-z0-9_-`，最长 64 |
| `json` | 可选。返回 JSON 而不是跳转 |

JSON 示例：

```bash
curl "https://你的站点/.../article_image.php?res=cover-01&json"
```

```json
{
  "key": "cover-01",
  "url": "https://cjsy.cc/wp-content/removeable/article_images/cover-01.webp",
  "ext": "webp"
}
```

也支持请求头 `Accept: application/json`。

## 文件 Files

| 文件 | 作用 |
|------|------|
| `article_image.php` | 接口入口（也是全部逻辑） |
| `config.php` | 站点配置（地址、扩展名、校验规则） |

## 配置 Config

编辑 `config.php`：

```php
return [
    // 图片目录（跳转目标前缀，不要以 / 结尾）
    'base_url' => 'https://cjsy.cc/wp-content/removeable/article_images',

    // 本地图片目录（开启 check_local 时使用）
    // 空字符串 = 同目录下的 article_images/
    'local_dir' => '',

    // true：本地没有该图则 404
    // false：总是跳转（默认，适合图在远端 WordPress）
    'check_local' => false,

    // res 参数允许的字符（白名单）
    'key_pattern' => '/^[A-Za-z0-9_-]{1,64}$/',

    // 扩展名
    'ext' => 'webp',

    // 参数错误时是否显示「请手下留情」说明
    'show_mercy_note' => true,
];
```

## 部署 Deploy

### 方式 A：WordPress 站点内（推荐，和原用法一致）

1. 把 `article_image.php`、`config.php` 放到：

```text
/wp-content/removeable/article_image.php
/wp-content/removeable/config.php
```

2. 图片放在：

```text
/wp-content/removeable/article_images/cover-01.webp
```

3. 访问：

```text
https://你的站点/wp-content/removeable/article_image.php?res=cover-01
```

若图片就在旁边的 `article_images/`，可把 `check_local` 设为 `true`，不存在的图会返回 404，而不是跳到空地址。

### 方式 B：独立小站反代/跳转

把两个 PHP 放到任意 PHP 空间，改 `base_url` 指向真实图片地址即可。

## 相比原脚本的安全改进

| 原来 | 现在 |
|------|------|
| 黑名单剔除一堆符号，漏了就可能出问题 | **白名单**正则，只允许合法图片名 |
| 未校验空参数 / 过长参数 | 非法或超长直接 400 |
| 图片不存在也可能 302 | 可选本地检查，404 |
| 错误页无独立样式 | 规范 HTML / 可选 JSON |
| 无响应头标记 | 带 `X-Article-Image-Key` |

## License

MIT（见 `LICENSE`）。图片版权仍归原作者。
