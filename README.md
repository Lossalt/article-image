# article-image

WordPress 文章配图按名跳转接口，支持 **webp / jpg / jpeg / png / gif / avif**。

打开 `article_image.php?res=图片名` → `302` 跳到对应图片。

Named WordPress article-image redirect API with multi-format support.

## 用法 Usage

```text
# 不带扩展名：本地有图则自动探测格式；没有则用 default_ext
https://你的站点/wp-content/removeable/article_image.php?res=cover-01

# 带扩展名：精确指定格式
https://你的站点/wp-content/removeable/article_image.php?res=cover-01.jpg
https://你的站点/wp-content/removeable/article_image.php?res=cover-01.png

# JSON 元数据
https://你的站点/wp-content/removeable/article_image.php?res=cover-01&json
```

| 参数 | 说明 |
|------|------|
| `res` | 图片名，可带扩展名。默认允许 `A-Za-z0-9_-`，最长 64；扩展名需在 `allowed_exts` 内 |
| `json` | 可选。返回 JSON 而不是跳转 |

JSON 示例：

```json
{
  "key": "cover-01.jpg",
  "base": "cover-01",
  "url": "https://cjsy.cc/wp-content/removeable/article_images/cover-01.jpg",
  "ext": "jpg",
  "file": "cover-01.jpg",
  "source": "local"
}
```

- `source: local` — 本地目录里探测到了文件  
- `source: default-ext` — 本地没有或未开检查，使用 `default_ext` 拼接  

也支持请求头 `Accept: application/json`。

## 文件 Files

| 文件 | 作用 |
|------|------|
| `article_image.php` | 接口入口（全部逻辑） |
| `config.php` | 站点配置 |

## 配置 Config

编辑 `config.php`：

```php
return [
    'base_url' => 'https://cjsy.cc/wp-content/removeable/article_images',

    'local_dir' => '',          // '' = 同目录 article_images/
    'check_local' => false,     // true：找不到就 404

    // res 允许：name 或 name.ext
    'key_pattern' => '/^[A-Za-z0-9_-]{1,64}(\.[A-Za-z0-9]{1,8])?$/',

    // 支持的格式（自动探测按此顺序找第一个存在的）
    'allowed_exts' => ['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif'],

    // 无扩展名且探测不到时用它
    'default_ext' => 'webp',

    'show_mercy_note' => true,
];
```

### 多格式规则

| 请求 | 本地有对应文件 | 结果 |
|------|----------------|------|
| `res=a` | 依次找 `a.webp` `a.jpg` `a.png`… | 跳到第一个找到的 |
| `res=a.jpg` | 找 `a.jpg` | 跳到 `a.jpg`（没有则 404 / default） |
| `res=a` | 都找不到，`check_local=false` | 跳到 `a.{default_ext}` |
| `res=a` | 都找不到，`check_local=true` | 404 |

## 部署 Deploy

### 方式 A：WordPress 站点内

```text
/wp-content/removeable/article_image.php
/wp-content/removeable/config.php
/wp-content/removeable/article_images/cover-01.jpg
/wp-content/removeable/article_images/cover-02.png
```

访问：

```text
.../article_image.php?res=cover-01.jpg
.../article_image.php?res=cover-02        # 自动探测到 .png
```

建议 `check_local = true`，这样缺图返回 404 而不是跳到空地址。

### 方式 B：独立跳转

两个 PHP 放到任意 PHP 空间，改 `base_url` 即可。图在远端时：

- 带扩展名的 `res=a.png` 最准确  
- 不带扩展名则用 `default_ext`（默认 webp）

## 安全 Security

- 白名单校验图片名与扩展名（不是黑名单剔符号）  
- 拒绝控制字符、路径穿越  
- 本地解析时用 `realpath` 限制在图片目录内  

## License

MIT（见 `LICENSE`）。图片版权仍归原作者。
