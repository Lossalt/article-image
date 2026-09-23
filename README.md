# article-image

**可搬迁图床的命名入口**（removable image bed）：文章里只写稳定图片名，物理文件可以放在本机 WordPress 目录、另一台服务器或对象存储；换机/迁站时改一行 `base_url`，正文不必改写。

支持 **webp / jpg / jpeg / png / gif / avif**。打开 `article_image.php?res=图片名` → `302` 跳到对应图片。

A movable / removable image-bed entry point for WordPress posts. Articles store a stable image name, not a physical path.

## 设计意图 Why removable

传统 WordPress 媒体库会把**绝对地址**写进正文：

```text
文章 HTML → https://旧站/wp-content/uploads/2024/06/foo.jpg
```

换服务器、换域名、换目录、整站迁移时，正文里的路径全部失效，图床一搬文章就「丢图」。

本项目把引用拆成两层：

```text
文章正文（稳定）
    article_image.php?res=cover-01
            │
            │  302（只改配置即可换指向）
            ▼
图床（可拆、可搬、removeable）
    wp-content/removeable/article_images/   ← 可以不在这
    或 https://图床服务器/...
    或对象存储 / CDN
```

| 约定 | 作用 |
|------|------|
| 路径里的 `removeable` | 提醒：图床目录是**可拆卸**的附属件，不绑死在当前 WP |
| `?res=名字` | 正文只记**逻辑名**，不记物理路径 |
| `base_url` 一行配置 | 图迁到另一台机器/另一个域名，只改这里 |
| `article_image.config.php` 独立文件名 | 入口可贴在 WP，配置与图可一起搬走，且不易和别的 `config.php` 撞名 |

一句话：**解耦「文章里的引用」和「图实际放哪」**，让图床可移动、整站迁移不丢图。

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
| `article_image.config.php` | 站点配置 |

## 配置 Config

编辑 `article_image.config.php`：

```php
return [
    'base_url' => 'https://cjsy.cc/wp-content/removeable/article_images',

    'local_dir' => '',          // '' = 同目录 article_images/
    'check_local' => false,     // true：找不到就 404

    // res 允许：name 或 name.ext
    'key_pattern' => '/^[A-Za-z0-9_-]{1,64}(\.[A-Za-z0-9]{1,8})?$/',

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
/wp-content/removeable/article_image.config.php
/wp-content/removeable/article_images/cover-01.jpg
/wp-content/removeable/article_images/cover-02.png
```

访问：

```text
.../article_image.php?res=cover-01.jpg
.../article_image.php?res=cover-02        # 自动探测到 .png
```

建议 `check_local = true`，这样缺图返回 404 而不是跳到空地址。

### 方式 B：图床在另一台服务器（removable 的典型用法）

WordPress 站只放两个 PHP：

```text
/wp-content/removeable/article_image.php
/wp-content/removeable/article_image.config.php
```

图片放在别的机器（或对象存储），只改配置：

```php
'base_url' => 'https://img.example.com/article_images',
'check_local' => false,
```

正文写法完全不用变。以后图床再搬家，仍然只改 `base_url`。

图在远端时：

- 带扩展名的 `res=a.png` 最准确  
- 不带扩展名则用 `default_ext`（默认 webp）

### 方式 C：整站迁移 checklist

1. 导出/迁移 `article_images/` 里的文件（或上传到新图床）  
2. 新环境部署 `article_image.php` + `article_image.config.php`  
3. 修改 `base_url`（以及需要时的 `local_dir`）  
4. **不用**批量替换文章正文里的图片地址

## 安全 Security

- 白名单校验图片名与扩展名（不是黑名单剔符号）  
- 拒绝控制字符、路径穿越  
- 本地解析时用 `realpath` 限制在图片目录内  

## License

MIT（见 `LICENSE`）。图片版权仍归原作者。
