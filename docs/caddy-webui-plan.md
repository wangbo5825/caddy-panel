# Caddy Web UI（PHP + Vue）系统规划 v1.0

> 状态：功能定稿 v1.0（2026-09-05）；原型页面设计稿见 §7
> 定位：独立、可复用的 Caddy 管理界面；先服务 FRAMPP，但不绑定 FrankenPHP，面向标准 PHP
> 调研时间：2026-08-29；信息以各项目 GitHub / Docker Hub / Caddy 社区帖子当时内容为准
> 语言：系统（界面 / CLI / 安装向导 / API 消息）与对外文档默认英语，中文为第二语言；
> 本文档为中文工作语言的设计规格，对外简介见 README.md（EN）与 README.zh-CN.md（ZH）。

## 变更记录

| 版本 | 日期 | 变更 |
| --- | --- | --- |
| v0.1 | 2026-08-29 | 初版规划：调研、功能分层、技术方案 |
| v0.2 | 2026-08-29 | 功能定稿：三层 Caddyfile 编辑 + 双校验；认证简化为本地免认证 + 单管理员；证书改为查看 + 导入/更换；引入 Composer（Slim 4）；命名暂定 caddy-panel |
| v0.3 | 2026-08-30 | 运维能力强化：三层备份模型（配置/站点/数据库钩子）、日志安全分析（攻击 IP/扫描 URL/频繁错误）、存储空间管理（占用/配额/清理/告警）；新增安全分析、存储、备份页面原型 |
| v0.4 | 2026-08-30 | 日志与安全强化：日志设置支持多输出/多粒度（全局+站点级、格式/级别/轮转/字段）；黑名单管理独立成页，安全分析一键加入，支持作用域、有效期、来源与启停 |
| v0.5 | 2026-08-30 | 认证与配置细化：远程访问单管理员密码登录；密码支持 CLI / 配置文件 / 设置页三种重置；Admin API 地址与面板监听端口写入 config.php |
| v0.6 | 2026-08-30 | 部署模型定稿：发布物为可上传到任意 PHP Web 服务器的预构建包，纯 Web 部署不依赖 CLI（初始化/改密/定时任务均有 Web 或配置等价途径）；CLI 与 Docker 为可选增强 |
| v0.7 | 2026-08-30 | 安装引导定稿：webroot 仅放启动器与前端产物，真实应用位于 APP_ROOT；Web 安装向导校验路径并初始化 config.php / var / etc，全流程免 SSH；CLI 仅用于恢复与高级运维 |
| v0.8 | 2026-08-30 | 安装包获取三模式定稿：APP_ROOT 独立上传 / 服务端下载（必填 SHA-256）/ 页面上传；向导分步 + 包校验 + 版本兼容检查，形成免 SSH 闭环 |
| v0.9 | 2026-09-05 | Caddyfile import 模式与运行目录（cwd）定稿：可选原生 import 模式与拼接模式并存；cwd 获取链为 FrankenPHP 自动 / 用户配置 / 同机探测；记录 API 与磁盘加载的 import 语义差异 |
| v1.0 | 2026-09-05 | 紧急恢复机制：全量快照（主文件+全局+片段）+ last-good 标记；CLI `snapshot / restore / doctor`；systemd ExecStartPre 与 Docker entrypoint 启动前自检示例；恢复文档（EN/ZH） |
| v1.1 | 2026-09-05 | Admin API 连接支持 Unix socket（`unix://`，设置项，默认仍为 TCP `127.0.0.1:2019`）；新增独立 PHP 与 FrankenPHP 安装文档 INSTALL.md（EN/ZH），恢复说明并入 RECOVERY.md |

---

## 1. 背景与目标

### 1.1 出发点

现存的 Caddy Web 管理界面分散在多种技术栈里：Go（CaddyDash、CaddyUI）、Node（CaddyManager、CaddyProxyManager）、Flutter（makinghappen/caddy-ui）、Next.js（caddy-admin-ui）等。它们功能强的很重（引入 DB、消息队列、分析引擎），轻的又只覆盖单一实例。

FRAMPP 需要的是一个：

- **与既有运行时一致**的控制面板（FRAMPP 本身就是 PHP 生态，控制面板已用 PHP 实现）；
- **不依赖 Node/Python/Go 运行时**、可随包离线分发的管理界面；
- 通过 **Caddy Admin API** 管理 Caddy，必要时编辑 Caddyfile；
- **本地访问免认证**（默认 127.0.0.1 绑定），远程访问可启用登录；
- 现代风格（Vue 前端）、中英双语、响应式。

### 1.2 目标

1. 做成**独立项目**：标准 PHP 8.1+（CLI server / FPM / FrankenPHP 均可运行），不绑定 FrankenPHP 特有能力。
2. Caddyfile 作为**唯一事实来源**：站点以 `caddy.d/<name>.caddy` 片段落盘，主 Caddyfile 由面板组装，通过 `/adapt` 校验、`/load` 热加载；非法配置保持旧配置继续运行。
3. 先做到“**90% 日常管理不需要碰 Caddyfile**”，同时保留“**高级用户直接改 Caddyfile/JSON**”的入口。
4. 与 FRAMPP 复用：作为 FRAMPP `modules/control-panel` 的演进版本，同时可独立部署到任意标准 PHP 环境。
5. 独立项目命名暂定 **`caddy-panel`**（正式发布前可再议，见 §11）。

---

## 2. 现状调研：现有 Caddy UI

### 2.1 项目清单与功能

| 项目 | 技术栈 | 定位/亮点 | 成熟度 | 许可证 |
| --- | --- | --- | --- | --- |
| [CaddyDash](https://github.com/WJQSERVER-STUDIO/caddydash) | Go + SQLite + 原生 HTML/JS | 轻量单实例：站点表单（反代/静态）、全局 Caddyfile 编辑、实例启停、i18n 中英、明暗主题、登录初始化、预设 Header | Pre-Alpha | MPL-2.0 |
| [CaddyManager](https://github.com/caddymanager/caddymanager) | MEVN（Vue/Express/MongoDB） | 多 Caddy 实例管理、表单模板建站、API Key、多用户（admin/user）、审计日志；路线图：仪表盘、批量操作、配置版本化、Git/S3 导入导出、OIDC | Alpha | MIT |
| [CaddyManager (rhad00)](https://github.com/rhad00/CaddyManager) | Node + Express + React | 反代管理：服务模板（Authelia/Keycloak/Nextcloud/Grafana…）、Cloudflare DNS-01、一键安全头、负载均衡+健康检查、访问日志（SSE）、告警通知、Docker 自动发现、GitOps、自动备份、2FA/API Key、OpenAPI | Alpha | – |
| [CaddyProxyManager](https://github.com/fuomag9/caddy-proxy-manager) | Next.js + React + Drizzle + ClickHouse | 功能最全：代理主机、L4 TCP/UDP、路径规则、重定向/重写、Forward Auth 门户、WAF（Coraza + OWASP CRS）、Geo 拦截、mTLS+RBAC、多角色用户、OAuth/OIDC、17 家 DNS Provider、实例同步、审计、REST API、流量分析 | 较活跃 | – |
| [CaddyUI (applegater)](https://github.com/X4Applegate/caddyui) | 独立管理应用（单二进制 + SQLite） | 代理/重定向/高级路由、Caddyfile 导入并自动归类、证书与 DNS 记录管理（DNS-01）、多 Caddy 服务器、健康监控、快照恢复、分析、通知、AI 助手、REST API + MCP、角色/所有权/2FA、PWA | 2.x，活跃 | – |
| [caddy-ui (makinghappen)](https://github.com/makinghappen/caddy-ui) | Flutter | 跨平台（桌面/移动/Web）树形配置编辑器、实时应用、证书管理、状态监控 | 早期 | MIT |
| [caddy-ui (rielcfb)](https://github.com/rielcfb/caddy-ui) | Web 前端 | 直接编辑 Caddy JSON/YAML 配置 | 旧 | – |
| [caddy-ui (zackwag)](https://hub.docker.com/r/zackwag/caddy-ui-frontend) | 前后端分离 | 后端代理 Admin API 并管理磁盘 Caddyfile；前端：仪表盘、配置编辑、路由管理、日志 tail | – | – |
| [caddy-admin-ui (gsmlg-dev)](https://github.com/gsmlg-dev/caddy-admin-ui) | Next.js | 轻量 Caddy 管理前端 | 低活跃 | – |
| GrottoPress/caddy-admin | WordPress 插件 | 非独立面板，略 | – | – |

### 2.2 共性能力归纳（各项目反复出现的功能）

1. **站点/代理主机表单**：域名、upstream、路径规则、Header、WebSocket、重定向——几乎所有项目都有，是刚需。
2. **证书**：查看/到期提醒是标配；上传自定义证书、DNS-01 是进阶。
3. **负载均衡与健康检查**：CaddyManager(rhad00)、CPM、CaddyUI 均有；Caddy 原生支持，面板做配置表单 + `/reverse_proxy/upstreams` 展示。
4. **访问控制**：IP 黑白名单 / CIDR / Geo 拦截（CaddyDash 预设 Header、CPM 全套、FRAMPP 的 access-filter 路线一致）。
5. **日志**：在线查看 / tail / 检索（CaddyManager、zackwag、nginx-ui）。
6. **备份与回滚**：配置快照、对比、一键恢复（CaddyManager、CPM、CaddyUI、nginx-ui）。
7. **多用户与权限**：admin/user/read-only、2FA、API Token（CaddyManager、CPM、CaddyUI）。
8. **自动化接口**：REST API + OpenAPI、MCP（CPM 生态、CaddyUI-MCP、nginx-ui MCP）。
9. **监控与统计**：实例状态、上游健康、流量分析（CaddyUI、CPM、nginx-ui）。
10. **通知告警**：证书到期、上游宕机（CaddyManager、CaddyUI）。
11. **多实例/集群**：CaddyManager(MEVN)、CaddyUI、nginx-ui、nginxWebUI。
12. **AI 助手**：nginx-ui（ChatGPT/DeepSeek）、CaddyUI（Ollama/OpenAI 兼容等）——试验性趋势。

---

## 3. 其他 Web 服务器 Web UI 功能清单（借鉴参考）

| 面板 | 服务器 | 技术栈 | 功能清单（可借鉴项） |
| --- | --- | --- | --- |
| [nginx-ui](https://github.com/0xJacky/nginx-ui) | Nginx | Go + Vue 3 | 服务器指标（CPU/内存/负载/磁盘）、配置自动备份+版本对比+恢复、集群管理（镜像同步多节点）、加密配置导出、LLM 助手（含 DeepSeek 思维链）、**MCP 接口**、Let's Encrypt 一键、NgxConfigEditor 块编辑 + Ace 编辑器（LLM 补全）、在线查看日志、保存后自动 `nginx -t` + reload、Web 终端、暗色、多语言、响应式 |
| [nginxWebUI](https://github.com/cym1102/nginxWebUI) | Nginx | Java（solon）+ SQLite | http/tcp 转发、反代、负载均衡、静态 HTML 上传、密码文件、IP 黑白名单、证书申请/续签（acme.sh）、**参数模板兜底**、备份历史回滚、远程多服务器管理、配置一键同步、日志采集/查看/统计、API 接口 |
| [Nginx Proxy Manager](https://nginxproxymanager.com/) | Nginx | Node + Angular | 代理主机、重定向主机、404 主机、TCP/UDP Stream、访问列表（Basic Auth + IP 限制）、Let's Encrypt/自定义证书、DNS Challenge、多用户 |
| [CyberPanel](https://cyberpanel.net/) | OpenLiteSpeed | PHP + Python | 站点、多 PHP 版本切换、LE SSL、DNS（PowerDNS）、邮件、FTP、phpMyAdmin、防火墙、一键备份/恢复、Docker 集成、文件管理、AI 安全扫描 |
| [HestiaCP](https://hestiacp.com/) | Apache + Nginx | Bash + PHP | 虚拟主机、DNS（Bind）、邮件+反垃圾、LE 通配符证书、防火墙/fail2ban、多用户、备份、cron、文件管理、2FA、一键应用 |
| [aaPanel/BT Panel](https://www.aapanel.com/) | Nginx/Apache/OpenLiteSpeed | Python | 网站、FTP、数据库、文件、cron、监控、防火墙、SSL、Docker、插件市场 |
| [Webmin/Virtualmin](https://www.virtualmin.com/) | Apache/Nginx | Perl + JS | 虚拟主机、DNS、邮件、数据库、备份、文件管理、SSH Web 终端、日志分析、多用户/ACL、2FA、API/CLI |
| OpenLiteSpeed WebAdmin | OpenLiteSpeed | PHP/C++ | 虚拟主机、监听器、SSL、缓存、rewrite、实时访问统计 |
| cPanel / Plesk | 多服务器 | 商业 | 主机全栈管理：站点、邮件、DNS、数据库、备份、安全、应用商店（作为能力上限参考） |

**结论**：无论哪个 Web 服务器，成熟面板的高频功能高度一致——站点表单、证书自动化、访问控制、日志、备份回滚、监控、多用户权限、API 自动化、通知。Caddy 因为**自动 HTTPS 和现代模块生态**，天然省掉证书申请、HTTP/2、WebSocket 等大量配置工作，面板可以比 Nginx 系更薄。

---

## 4. 功能必要性分析

### 4.1 必须（P0，MVP 闭环）

| # | 功能 | 说明 |
| --- | --- | --- |
| 1 | 实例仪表盘 | Caddy 运行状态、配置生效状态、监听端口、上游健康概览、面板/环境信息 |
| 2 | 站点管理（表单 CRUD） | 三种形态：静态文件服务、反向代理、PHP 应用（FRAMPP 场景；标准 Caddy 下 PHP 形态隐藏或提示不可用） |
| 3 | 站点启停 | enabled 开关，禁用 = 从 caddy.d 移除/注释 |
| 4 | 多域名/端口/根目录 | 站点基础字段 |
| 5 | 反代配置 | 多 upstream、WebSocket、请求/响应头、路径重写、超时 |
| 6 | Caddyfile 三层编辑 | 站点级高级模式（表单锁定，直接编辑原始块）/ 全局配置段 / 完全自定义模式（逃生舱，面板退出托管） |
| 7 | 校验与安全回滚 | 保存先 `/adapt` 预校验（带行号错误）→ 通过后 `/load` 应用；`/load` 失败自动回滚，旧配置保持运行 |
| 8 | 检查但不应用 | 仅跑 `/adapt`，返回适配后的 JSON 预览，不落盘不加载 |
| 9 | 配置备份/回滚 | 每次保存前自动快照，可查看历史并一键回滚 |
| 10 | 证书查看 | 域名、issuer、签发/过期时间；到期高亮提醒 |
| 11 | 访问日志查看 | 读取 Caddy 结构化日志（JSON），tail + 过滤（域名/状态码） |
| 12 | 本地免认证 | REMOTE_ADDR 为 127.0.0.1/::1 自动进入管理员模式（可配置关闭） |
| 13 | 远程登录 | 远程访问时单管理员账号密码登录；首次初始化（远程需一次性 setup token）；登录限速与失败锁定 |
| 14 | 密码重置 | 三种途径：CLI 命令 `php bin/panel passwd`、配置文件一次性重置、设置页改密（需当前密码） |
| 15 | 界面基础 | Vue 3 + 明暗主题 + 响应式 + 中英 i18n |

### 4.2 应有（P1）

| # | 功能 | 说明 |
| --- | --- | --- |
| 16 | 证书导入/更换 | 上传 PEM 证书/私钥并应用到指定站点；支持替换现有证书与“还原 Caddy 自动证书” |
| 17 | 负载均衡/健康检查配置 | 策略（round_robin/least_conn/random/ip_hash）、主动/被动健康检查 |
| 18 | 上游健康监控 | `/reverse_proxy/upstreams` + 面板自探测，列表页显示健康状态 |
| 19 | 访问控制 | IP/CIDR/国家码规则（对接 caddy-access-filter；无该模块时面板隐藏或降级提示） |
| 20 | 高级路由 | 路径匹配规则、重定向（301/302/307/308）、Header 操作 |
| 21 | API 与 Token | 单管理员级 REST API + 作用域 Token（read/write/admin），为 CI/CD、MCP、FRAMPP Agent 预留 |
| 22 | 导出/导入 | Caddyfile 全文、Caddy JSON、站点模板、站点文件包导出导入 |
| 23 | 通知 | 证书到期、实例异常、空间阈值、安全告警：邮件 / Webhook / ntfy |
| 23 | 端口/进程信息 | 端口占用检测、Caddy 进程与面板自身状态 |
| 24 | 站点备份/恢复 | 手动 + 定时；站点根目录打包（排除规则），恢复前自动快照，可恢复到原目录或新目录 |
| 25 | 日志安全分析 | 攻击 IP / 扫描 URL / 频繁错误聚合，阈值告警，一键加入访问控制黑名单 |
| 26 | 存储空间管理 | 磁盘/日志/备份/站点占用统计；站点配额；日志轮转与备份保留清理策略 |
| 27 | 容量告警 | 全局阈值（80% 警告 / 90% 紧急）、站点软硬配额、空间不足暂停备份并告警 |
| 28 | 日志设置 | 全局与站点级日志配置：多输出（文件 / stdout / 网络）、格式（json/console）、级别、log_roll 轮转、字段裁剪 |
| 29 | 黑名单管理 | 独立黑名单：IP/CIDR、作用域（全局/站点）、有效期、来源（手动 / 安全分析一键加入）、启停与审计 |

### 4.3 可选（P2，视反馈决定）

| # | 功能 | 说明 |
| --- | --- | --- |
| 30 | 多 Caddy 实例 | 远程实例连接配置（仅建议内网/VPN），资源按实例隔离 |
| 31 | 流量统计 | 基于访问日志聚合：请求数、带宽、状态码分布、Top 域名/路径；保留期可配（与安全分析共用数据） |
| 32 | Web 终端 | 面向高级用户（安全性敏感，默认关闭） |
| 33 | Docker 自动发现 | 容器标签自动生成站点（FRAMPP 暂不需要） |
| 34 | GitOps | 配置变更自动 commit / 从 Git 拉取（FRAMPP 暂不需要） |
| 35 | AI 助手 | LLM 解释配置、生成 Caddyfile 片段（接入 Ollama/OpenAI 兼容） |
| 36 | DNS-01/DNS 记录管理 | 托管 DNS 凭据与记录（CaddyUI 有完整实现，属于重量功能） |
| 37 | mTLS / Forward Auth 配置表单 | 依赖 Caddy 模块；面板只提供表单入口，不实现门户本身 |
| 38 | 数据库备份钩子 | FRAMPP 场景可选：mysqldump/mariadb-dump 定时备份，脚本钩子方式接入 |
| 39 | 异地备份目标 | 备份同步到 SFTP / S3 兼容存储，降低同盘失效风险 |

### 4.4 明确不做（非目标）

- **内置 WAF 引擎**：WAF 是 Caddy 模块（Coraza 等）的职责，面板最多提供配置入口。
- **替代 Caddy 的 TLS/ACME 逻辑**：证书签发、续期、存储全部交给 Caddy。
- **数据库管理**：FRAMPP 由 Adminer 承担。
- **主机控制面板**：不做邮件、DNS 服务器、FTP、系统用户管理（那是 CyberPanel/HestiaCP 范畴）。
- **PHP 之外的运行时**：不引入 Node/Python/Go 依赖（AI 助手若做，仅作为可选外部调用）。
- **多用户体系**：无角色/用户管理，仅单一管理员（本地免认证 + 远程登录）。

---

## 5. 目标功能列表（供讨论的正式清单）

### P0 · MVP（第一版可交付）

**A. 仪表盘**
- A1 显示 Caddy 运行状态（进程存在性/Admin API 可达性）
- A2 显示当前生效配置摘要（站点数、监听端口、自动 HTTPS 状态）
- A3 显示面板版本、Caddy 版本（可探测时）、运行环境
- A4 空间概览（磁盘使用率、日志/备份/站点占用）与容量告警横幅

**B. 站点管理**
- B1 站点列表（名称、域名、形态、状态、启停开关）
- B2 新建/编辑/删除站点，形态：`static` / `reverse_proxy` / `php`（FRAMPP）
- B3 站点字段：域名（多值）、监听地址、根目录、index 文件、TLS 开关、站点级自定义指令
- B4 反代字段：upstream 列表、WebSocket 开关、请求/响应 Header、路径前缀重写、超时/重试
- B5 站点启停、复制、导出为模板
- B6 站点级高级模式：表单锁定，直接编辑该站点原始 Caddyfile 块

**C. 配置管理**
- C1 三层编辑：站点级高级模式（原始块）、全局配置段（高亮、行号）、完全自定义模式（逃生舱）
- C2 保存流程：本地组装 → `/adapt` 校验（带行号错误）→ 通过后 `/load` 应用；失败自动回滚，旧配置保持运行
- C3 检查但不应用：仅 `/adapt` 并返回 JSON 预览
- C4 每次变更自动快照；历史列表（时间、操作者、差异预览）；一键回滚
- C5 当前运行 JSON 配置只读预览

**D. 证书**
- D1 证书列表（域名、issuer、签发/过期时间）
- D2 到期高亮与面板内提醒
- D3 手动上传 PEM 证书/私钥并应用到指定站点
- D4 替换现有证书；一键“还原 Caddy 自动证书”

**E. 日志**
- E1 访问日志 tail（最新 N 条，自动刷新）
- E2 过滤：域名、状态码、时间范围；错误日志独立入口

**F. 认证与安全**
- F1 首次访问初始化单一管理员账号；本机访问可引导初始化，远程初始化需一次性 setup token（CLI 生成）
- F2 本地访问（127.0.0.1/::1）自动登录为管理员；开关可配置
- F3 远程访问需登录；会话 Cookie（HttpOnly + SameSite）；登录限速 + 失败锁定（如 5 次失败锁 15 分钟）
- F4 所有写操作审计日志（单管理员视角）
- F5 不引入多用户体系（无角色/用户管理）
- F6 密码重置三途径：CLI（`php bin/panel passwd`，交互或参数）、配置文件一次性重置、设置页改密（需当前密码）；密码以 Argon2id 哈希存储

**G. 界面**
- G1 Vue 3 + 组件库；明暗主题；移动端响应式
- G2 i18n：简体中文 / English 起步

### P1 · 增强（第二版）

**H. 代理增强**
- H1 负载均衡策略配置（多 upstream）
- H2 健康检查配置（主动/被动；间隔、阈值）
- H3 上游健康状态展示（列表页 + `/reverse_proxy/upstreams`）
- H4 路径匹配规则（location → 不同 upstream）、重定向规则、Header 操作

**I. 访问控制**
- I1 IP / CIDR / 国家码规则页（检测到 `access-filter` 模块时可用）
- I2 规则应用范围：全局 / 站点级；热重载
- I3 无模块时面板降级提示（Windows 官方 FrankenPHP 场景）

**J. 自动化**
- J1 REST API + 作用域 Token（read / write / admin）——不依赖多用户体系
- J2 OpenAPI 文档页
- J3 审计日志浏览（P0 只记录，P1 可查）

**K. 数据管理**
- K1 站点/配置导出导入（Caddyfile、JSON、站点文件包）
- K2 快照保留策略与手动快照
- K3 通知：证书到期、实例下线（邮件 / Webhook / ntfy）
- K4 证书导入/更换入口（与 P1 #15 配套）

**M. 备份与恢复（v0.3）**
- M1 三层备份模型：配置快照 / 站点文件备份 / 数据库备份钩子（P2），独立保留策略
- M2 站点备份：手动 + 定时（每日/每周），排除规则，大小校验，空间不足时暂停并告警
- M3 恢复：恢复前自动快照、可恢复到原目录或新目录、恢复后校验、全程审计

**N. 日志安全分析（v0.3）**
- N1 增量解析 Caddy 结构化日志到 SQLite 聚合表（默认保留 14 天，可配）
- N2 攻击 IP / 扫描 URL / 频繁错误（5xx）排行与趋势
- N3 阈值告警（邮件 / Webhook / ntfy）与一键加入访问控制黑名单

**O. 存储空间管理（v0.3）**
- O1 磁盘/日志/备份/站点占用统计（周期扫描 + 缓存）
- O2 站点配额与全局阈值（80% 警告 / 90% 紧急）
- O3 日志轮转与备份保留清理（自动清理仅限日志与过期备份，站点文件只提示不自动删除）

**P. 日志设置（v0.4）**
- P1 全局与站点级日志配置（Caddy `log` 指令语义，站点级覆盖全局）
- P2 多输出目标：文件 / stdout / 网络端点，输出列表管理
- P3 格式（json/console）、级别过滤、字段裁剪
- P4 log_roll 轮转（大小 / 天数 / 压缩）与存储策略联动
- P5 日志查看器与安全分析选择同一数据源

**Q. 黑名单管理（v0.4）**
- Q1 独立黑名单：IP/CIDR 条目，作用域（全局/站点）
- Q2 有效期（临时封禁可设时长）与来源标记（手动 / 安全分析一键加入）
- Q3 启停、删除与审计；写入 access-filter 规则并热重载
- Q4 无 access-filter 模块时降级为提示（仅记录不拦截）

### P2 · 可选

- L1 多 Caddy 实例（远程连接，限内网）
- L2 流量统计（访问日志聚合 + 图表）
- L3 Web 终端（默认关闭）
- L4 AI 助手（解释/生成 Caddyfile；可选接入）
- L5 DNS-01 / 托管 DNS（若有强需求）
- L6 mTLS / Forward Auth 表单入口
- L7 Docker 自动发现 / GitOps

---

## 6. 技术方案概要

### 6.1 总体架构

```text
浏览器 (Vue 3 SPA)
   │  HTTPS/HTTP
   ▼
PHP 后端 (REST API)
   ├── Admin API 客户端 ──► Caddy Admin API (127.0.0.1:2019)
   │                         /config /load /adapt /stop /reverse_proxy/upstreams
   ├── 文件系统
   │     etc/caddy.d/<name>.caddy   站点片段（唯一事实来源）
   │     etc/Caddyfile              主配置（import 片段，面板组装）
   │     logs/                      访问/错误日志
   │     var/backups/               配置快照
   └── SQLite                       用户/会话/站点元数据/快照索引/审计
```

### 6.2 关键决策

1. **Caddyfile 是唯一事实来源，不落 JSON**：站点=片段文件（`caddy.d/<站点>.caddy`）；**默认主 Caddyfile 通过 `import`（绝对路径）引入 `caddy.d/*.caddy`**，站点新增/编辑即写片段文件；提供"单文件拼接"兜底（远程 Admin API、Caddy 无法读取本地片段文件的场景）。保存时先 `/adapt` 校验（Caddy 返回带行号错误），成功再 `/load` 应用。Caddy 自身 `--config` 启动仍指向同一 Caddyfile，重启后状态一致，避免“DB 与文件配置漂移”（FRAMPP 蓝图已确认此路线）。
2. **元数据用 SQLite**（PHP `pdo_sqlite`，零外部服务）：存用户、会话、站点排序/分组、快照索引、审计。配置本身不入库。
3. **Admin API 只经 PHP 后端代理**：浏览器不直连 2019；写操作统一走后端（可加审计、CSRF、权限）。Caddy 保持仅监听 localhost。
4. **本地免认证 + 单管理员**：后端判定 `REMOTE_ADDR` ∈ {127.0.0.1, ::1} 且配置允许时，直接建立管理员会话；远程访问走单管理员账号密码。可配置 `auth.allow_local_auto_login=false` 强制登录；远程首次初始化需 CLI 生成的一次性 setup token；密码支持 CLI / 配置文件 / 设置页三种重置（Argon2id 哈希存储）。不实现多用户/角色。
5. **PHP 依赖策略（定稿）**：引入 Composer 轻量依赖（Slim 4 路由 + PSR-7），`vendor/` 随包打包，运行时离线可用；不引入完整框架。
6. **前端**：Vue 3 + TypeScript + Vite + Naive UI（或 Element Plus）+ Pinia + Vue Router；构建产物为静态文件，随包分发。
7. **站点形态自适应**：检测 Caddy 构建（如 `/config` 中模块信息）决定 `php_server` 是否可用；标准 Caddy 仅显示 static / reverse_proxy。
8. **三层编辑粒度（定稿）**：站点级高级模式（表单锁定，直接编辑原始块）；全局配置段编辑；完全自定义模式（面板退出站点托管，站点列表只读，仅保留单 Caddyfile 编辑/校验/加载）。保存统一走 `/adapt` → `/load` 双校验，并提供“检查但不应用”。
9. **命名（暂定）**：`caddy-panel`；正式发布前可改，见 §11。
10. **运维能力（v0.3）**：站点备份/恢复、日志安全分析、存储空间管理纳入 P1，设计详见 §8。
11. **日志与安全（v0.4）**：多日志设置（多输出/多粒度）与黑名单管理纳入 P1，设计详见 §8。
12. **Admin API 地址可配置（配置文件优先）**：默认 `127.0.0.1:2019`；Caddy 侧支持 `CADDY_ADMIN` 环境变量或 Caddyfile 全局 `admin` 指令覆盖（Caddyfile 配置优先于环境变量）。面板侧写入 `config.php` 的 `caddy.admin_api`，可在设置页修改并测试连通；面板生成的 Caddyfile 同步写入 `admin` 指令；修改后需重启 Caddy 生效；绑定非回环地址时给出安全警告。
13. **面板自身端口可配置**：独立部署（`php -S`）时监听地址由 `config.php` 的 `panel.listen` 指定；生产环境一般由 Caddy/FPM 反代，不占用该端口。
14. **纯 Web 部署是硬约束（web-only first）**：所有管理操作都有 Web / 配置文件等价途径，CLI 仅为有 shell 环境的增强；定时任务用内置请求触发调度器（类似 wp-cron，带并发锁）+ 可选外部 cron ping。
15. **发布物**：预构建 zip（前端 dist + PHP 后端 + vendor + bin/panel + 部署示例），上传任意 PHP 8.1+ Web 服务器即可运行，webroot 为 `public/`；可选 Docker 镜像（详见 §9.1）。
16. **安装引导（webroot 启动器 + APP_ROOT 分离）**：webroot 只放 `index.php` / `install.php` / `panel.bootstrap.php` 与前端产物；真实应用位于用户配置的 `APP_ROOT`。启动器校验路径 → 未安装则进入 Web 安装向导（初始化 config.php、var/、etc/ 与管理员账号）；CLI 仅作恢复与高级运维（详见 §9.2）。
17. **三种包获取模式**：APP_ROOT 独立上传 / 服务端下载（必填 SHA-256）/ 页面上传；向导分步执行并做包校验与版本兼容检查，形成“改 APP_ROOT → 传页面 → 启动向导 → 获取包 → 配置 → 完成”免 SSH 闭环（详见 §9.2）。
18. **Caddyfile import 模式与 cwd（v0.9）**：提供原生 import 模式与拼接模式两种风格（默认方式由 #19 定稿）。cwd 获取链：FrankenPHP（PHP 与 Caddy 同进程，`getcwd()` 即 Caddy cwd）→ 用户设置 `caddy.cwd`（独立 PHP-FPM / php -S / 异机场景）→ 同机 `/proc/<pid>/cwd` 尽力探测。注意语义差异：Admin API 文本加载时 `import` 相对 **Caddy 进程 cwd** 解析，磁盘加载（`caddy run --config`）相对**主文件目录**解析；因此 import 模式默认生成**绝对路径 import**（两种加载语义下均正确），仅当 cwd 已知且用户要求可写相对 cwd 的 import，并在设置页标注该差异。
19. **默认采用 caddy.d + import（v1.0）**：虚拟主机（站点）以 `caddy.d/<站点>.caddy` 片段为主，主 Caddyfile 通过 `import "绝对路径/caddy.d/*.caddy"` 引入；片段不存在时省略 import。`concat` 拼接保留为设置项（远程 API / Caddy 无法读取本地片段的兜底）。
20. **紧急恢复（v1.0）**：每次应用前创建**全量快照**（`var/backups/config/<ts>/`：主文件 + global + 片段 + manifest，保留 30 份）；每次 `/load` 成功后更新 `var/last-good/`。CLI `php bin/panel snapshot / restore --list|--latest|--id|--last-good [--verify] / doctor [--fix]` 纯文件操作、不依赖 Web/Admin API；systemd `ExecStartPre` 与 Docker entrypoint 在启动前执行 `doctor --fix` 自动回滚。详见 docs/RECOVERY.md（EN/ZH）。
21. **Admin API 连接方式（v1.1）**：设置项支持 TCP（默认，`http://127.0.0.1:2019`）与 Unix socket（`unix:///run/caddy-admin.sock`，仅同机）；AdminClient 识别 `unix://` 前缀并设置 `CURLOPT_UNIX_SOCKET_PATH`。独立 PHP 与 FrankenPHP 安装方法见 docs/INSTALL.md（EN/ZH），恢复方法见 docs/RECOVERY.md（EN/ZH）。

### 6.3 目录结构（草案）

```text
caddy-panel/
├─ web/                    # Vue 3 前端源码
├─ src/php/                # PHP 后端
│  ├─ Router.php  Api.php  AdminClient.php  SiteManager.php
│  ├─ Auth.php  Audit.php  Backup.php  CaddyfileBuilder.php
│  └─ controllers/  models/
├─ public/                 # webroot：panel.bootstrap.php / index.php / install.php + 前端产物
├─ bin/                    # CLI：panel passwd / setup / snapshot 等
├─ etc/                    # 面板配置、Caddyfile 模板、caddy.d/
├─ var/                    # SQLite、快照、运行状态
├─ docs/
└─ tests/
```

---

## 7. 原型页面规划（设计稿 v0.2）

> 可交互 HTML 原型（工作目录 `design/`）：[caddy-panel-prototype.html](design/caddy-panel-prototype.html)（管理界面）与 [caddy-panel-install-prototype.html](design/caddy-panel-install-prototype.html)（安装向导，含三种包获取模式与闭环流程）。

### 7.1 信息架构

```text
caddy-panel
├─ 仪表盘 Dashboard
├─ 站点管理 Sites
│  ├─ 站点列表
│  └─ 站点编辑（基础 / 反代 / 高级模式）
├─ 访问控制 Access（P1，含黑名单管理）
├─ Caddyfile 配置
│  ├─ 全局配置段编辑
│  └─ 完全自定义模式（逃生舱）
├─ 证书 Certificates
│  └─ 导入 / 更换
├─ 日志 Logs（含日志设置）
├─ 安全分析 Security（P1）
├─ 存储 Storage（P1）
├─ 备份 Backups（P1）
└─ 设置 Settings
   ├─ 认证（本地免认证 / 管理员账号 / 修改密码）
   ├─ 面板（语言 / 主题）
   ├─ API Token
   ├─ Caddy 连接
   └─ 快照与备份
```

### 7.2 页面清单

| 页面 | 路由 | 核心区块 | 关键交互 |
| --- | --- | --- | --- |
| 仪表盘 | `/` | 实例状态、配置摘要、证书到期、最近审计 | 状态联动、快捷入口 |
| 站点列表 | `/sites` | 表格：名称/域名/形态/TLS/状态/操作 | 启停开关、编辑、新建、复制 |
| 站点编辑 | `/sites/:id` | 基础表单 / 反代表单 / 高级原始块 | 形态切换、高级模式锁定表单、保存校验流 |
| 访问控制 | `/access` | 规则表 + 黑名单管理（IP/CIDR、有效期、来源） | 全局/站点级作用域；安全分析一键加入；无模块时降级提示 |
| Caddyfile | `/caddyfile` | 编辑器 + 动作栏 + 校验结果面板 | 检查但不应用、保存并应用、错误定位 |
| 证书 | `/certificates` | 证书表 + 导入面板 | 导入/更换、还原自动证书 |
| 日志 | `/logs` | 过滤栏 + 实时日志流 + 日志设置 | tail、过滤；多输出 / 轮转 / 格式配置 |
| 安全分析 | `/security` | 攻击 IP / 扫描 URL / 频繁错误 Top 表 + 时间范围 | 一键加入黑名单（联动访问控制） |
| 存储 | `/storage` | 磁盘/日志/备份/站点占用、配额、清理策略 | 阈值告警、日志轮转、备份清理 |
| 备份 | `/backups` | 配置快照 + 站点备份列表、调度与保留 | 手动/定时备份、恢复、下载 |
| 设置 | `/settings` | 认证 / 面板 / Token / 连接 / 快照 | 本地免认证开关、改密、Token 生成、快照回滚 |
| 登录/初始化 | `/login` | 单管理员登录/初始化表单 | 首次初始化（本机免 token / 远程需 setup token）、远程登录 |

### 7.3 关键流程

1. **保存站点**：表单/高级块 → 重建片段 → 组装主 Caddyfile → `/adapt`（失败：带行号错误 + 保持旧配置）→ `/load`（失败：自动回滚 + 提示）→ 快照入库。
2. **高级模式**：开启后表单只读，编辑器显示该站点原始块；保存仍走同一校验流。
3. **完全自定义模式**：站点列表只读，面板仅保留“单个 Caddyfile 编辑 + 校验 + 加载”；退出需确认（放弃或导入手写块）。
4. **证书导入**：上传 PEM + 私钥 → 选择站点 → 保存后该站点切为手动证书；可一键还原自动证书。
5. **本地免认证**：`REMOTE_ADDR` = 127.0.0.1/::1 且开关开启 → 自动建立管理员会话，跳过登录页。
6. **站点备份**：选择站点/组 → 打包（排除规则）→ 校验大小 → 存入 `var/backups/sites/` → 按保留策略清理；恢复前自动快照当前状态，可恢复到原目录或新目录。
7. **日志分析**：Caddy 结构化日志增量导入 SQLite 聚合 → 定时计算攻击 IP/扫描 URL/错误排行 → 阈值命中触发告警；可一键把 IP 加入黑名单（条目带作用域、有效期与来源，可在访问控制页管理）。
8. **空间管理**：周期扫描磁盘/日志/备份/站点占用 → 超阈值告警；日志按大小/天数轮转，备份按保留策略清理（站点文件不自动删除）。
9. **日志设置**：全局与站点级配置多输出（文件 / stdout / 网络）、格式 / 级别、log_roll 轮转；保存后走 `/adapt` → `/load` 热加载。
10. **密码重置**：CLI `php bin/panel passwd` 直接改（需服务器 shell）；配置文件提供一次性重置（如 `auth.reset_password` 或密码文件，应用后清除）；设置页改密需当前密码。远程首次初始化必须出示 setup token。

### 7.4 视觉基调

- 现代化管理后台：左侧导航 + 顶栏（页面标题 / 实例状态 / 用户信息），内容区卡片化。
- Vue 3 + Naive UI 风格；明暗主题跟随系统；中英 i18n；移动端导航折叠为顶栏。
- 状态语义：运行中=绿、警告/将到期=琥珀、错误=红。

### 7.5 交互原型

交互式 HTML 原型（页面导航、高级模式、校验失败模拟、证书导入面板等）用于评审布局与交互，见最终回复中的可视化引用；原型不产生项目代码。

---

## 8. 运维设计强化：备份 / 日志分析 / 存储管理（v0.3）

### 8.1 备份与恢复：三层模型

| 层 | 对象 | 触发 | 存储 | 恢复 |
| --- | --- | --- | --- | --- |
| 配置快照 | Caddyfile 片段 + JSON + 站点元数据 | 每次保存前自动 | `var/backups/config/` | 一键回滚（秒级） |
| 站点文件备份 | 站点根目录（`htdocs/<site>` 等） | 手动 + 定时（每日/每周） | `var/backups/sites/<site>-<ts>.zip` | 恢复到原目录或新目录 |
| 数据库备份（P2） | MariaDB/MySQL dump | 定时（钩子） | 同上或外部目标 | 由 Adminer/脚本恢复 |

要点：

- **排除规则**：缓存/构建产物/日志（`cache`、`node_modules`、`vendor`、`var`、`logs`）默认排除，按站点可配置。
- **保留策略**：按份数或总大小上限，超限自动清理最旧备份（优先级高于一切站点文件清理）。
- **空间保护**：备份目录剩余空间低于阈值（如 <2GB 或 <10%）时暂停新建备份并告警。
- **恢复安全**：恢复前自动对当前状态做一次快照；支持恢复到新目录比对后再切换；恢复动作全程审计。
- **与配置回滚的区别**：配置回滚秒级、轻量；站点备份是数据级、较大，两者独立保留策略。

### 8.2 日志分析与安全监控

- **采集与存储**：Caddy 结构化 JSON 访问日志按文件轮转；面板每 5–10 分钟增量解析并写入 SQLite 聚合表（按小时/按日预聚合）；原始日志按空间策略保留（默认 14 天或 2GB，可配）。
- **分析维度（安全分析页）**：

| 维度 | 规则示例 |
| --- | --- |
| 攻击 IP | 5 分钟内 4xx ≥ 20 或 5xx ≥ 10；命中 access-filter 拒绝次数；按 `remote_ip` 聚合失败请求 |
| 扫描 URL | 404 高频路径（`/.env`、`/wp-admin`、`/phpinfo.php`、`/admin`、`/.git`）；非常规扩展名扫描 |
| 频繁错误 | status ≥ 500 按 域名+路径 聚合；错误率（5xx/总请求）趋势 |
| 常规概览 | Top 域名/路径/UA、状态码分布、请求量趋势（与 P2 流量统计共用数据） |

- **告警联动**：阈值命中 → 通知（邮件 / Webhook / ntfy）；页面一键把 IP 加入黑名单（写 access-filter 规则并热重载）。
- **黑名单管理**：访问控制页提供独立黑名单（IP/CIDR、作用域、有效期、来源标记：手动 / 安全分析、启停、审计）；安全分析一键加入后可设置临时封禁时长，无 access-filter 模块时降级为仅记录。
- **性能边界**：默认只对聚合结果做告警与展示；大数据量部署可关闭实时 tail，仅保留定时分析。

### 8.3 存储空间管理

- **采集**：周期（默认 30 分钟）统计磁盘总量/可用、日志目录、备份目录、各站点根目录占用；大目录扫描带缓存与增量。
- **展示**：存储页 + 仪表盘空间卡：磁盘使用率、日志/备份/站点占用 Top。
- **配额与告警**：全局阈值（80% 警告 / 90% 紧急）；站点可选配额（软 80% / 硬 95%）；日志目录上限触发轮转/清理；备份按保留策略清理。
- **安全边界**：自动清理仅限日志文件与过期备份；站点业务文件只提示不自动删除；所有清理动作进审计。

### 8.4 日志设置（多输出 / 多粒度）

- **作用域**：全局默认访问日志 + 站点级覆盖（Caddy `log` 指令语义）。
- **多输出**：同一日志可写多个目标（文件 / stdout / 网络端点），面板提供输出列表管理。
- **格式与级别**：`json` / `console`，级别过滤，字段裁剪（omit / 仅保留指定字段）。
- **轮转**：`log_roll` 按大小 / 天数 / 压缩，与存储策略联动（默认 14 天 / 2GB）。
- **读取联动**：日志查看器与安全分析选择同一数据源；配置保存走 `/adapt` → `/load` 热加载。

---

## 9. 与 FRAMPP 的关系

- FRAMPP 蓝图 §2.11 已定版“PHP 原生站点管理 UI”（`SiteManager.php` + `web/sites.php` + `caddy.d` 片段 + `/load` 热重载）。本规划将其**升级为独立、完整、可复用的 Caddy Web UI 项目**，FRAMPP 作为宿主之一（`modules/control-panel`），通过配置文件适配：
  - Caddy 可执行路径、Admin API 地址、`caddy.d` 目录位置；
  - 是否内置 `caddy-access-filter`（决定 IP 访问控制页是否可用）；
  - 是否支持 `php_server`（决定 PHP 站点形态是否显示）。
  - 认证：`auth.allow_local_auto_login`、管理员用户名、密码文件 / 一次性重置；
  - 面板自身监听地址 `panel.listen`（独立部署 `php -S` 时生效）。
- 独立部署模式：标准 PHP 8.1+，`php -S` 或 FPM 均可，`config.php` 指向任意 Caddy 的 Admin API。

### 9.1 发布物与纯 Web 部署（web-only first）

**发布物**：`caddy-panel-<版本>.zip`，内含 `public/`（前端构建产物 + `index.php`）、`src/`、`vendor/`（已打包 Slim 4 等依赖）、`bin/panel`（CLI，可选）、`config.sample.php`、部署示例与文档。解压上传即可，**无需 composer、无需 shell**；要求仅 PHP 8.1+（pdo_sqlite、openssl、curl）与任意 Web 服务器，webroot 指向 `public/`。

**纯 Web 环境（无 shell / 无 CLI）下的等价途径**：

- 首次初始化：安装向导 + 用户通过文件管理器放置一次性 `var/setup.token`（或在 `config.php` 写入 `auth.setup_token`），避免"先到先得"式开放注册。
- 忘记密码：`config.php` 一次性重置项（应用后移除），或已登录时设置页改密。
- 定时任务（日志导入、安全分析、备份、空间扫描）：内置请求触发调度器——每次请求检查到期任务并执行（SQLite 锁防并发）；可用外部 cron 请求 `/cron?key=<token>` 提升准时性；设置页可查看上次执行并手动触发。
- Caddy 交互：PHP 后端直连 Caddy Admin API（HTTP），**不依赖 shell**；面板与 Caddy 同机时走 localhost，异机时配置可访问的 admin 地址（内网或受控反代，含安全警告）。
- 备份 / 日志 / 空间管理：全部为 PHP 文件操作 + SQLite，无外部服务依赖。

**有 shell 环境的增强（可选）**：`php bin/panel`（passwd / setup / snapshot / cron 手动执行）、systemd timer 替代内置调度器、进程级 Caddy 启停（纯 Web 环境隐藏该能力）。

**部署示例**：随包提供 Caddy（`file_server` + `php_fastcgi`）、Apache（`.htaccess`）、Nginx（`try_files`）三种配置，Vue Router history 模式统一回退到 `public/index.php`。

**目录布局（安全约束）**：禁止把整个包直接放进 webroot——`config.php`（含 token/密钥）、`var/`（SQLite、快照、备份）与源码一旦被 Web 服务器直接分发即可被下载。两种正确布局：①自管服务器：包放 webroot 之外，站点根目录指向包内 `public/`；②共享主机（无法改 webroot）：webroot 内只放 `public/` 的内容，其余代码放 webroot 上一级不可被 HTTP 访问的目录，`index.php` 通过 `APP_ROOT` 路径常量引用。发布物需同时支持这两种布局。

### 9.2 安装引导（webroot 启动器 + APP_ROOT，免 SSH）

**目录职责**：

- webroot（站点根目录）：`panel.bootstrap.php`（唯一用户需修改的文件，仅含 `APP_ROOT` 路径）、`index.php`、`install.php`、前端静态产物。
- APP_ROOT（真实应用目录，位于 webroot 之外）：`src/`、`vendor/`、`etc/`、`var/`、`config.php`、`bin/panel`。安装包分两部分上传：webroot 内容传进站点根目录，`app/` 内容传到目标 APP_ROOT（FTP / 文件管理器即可完成，无需 SSH）。

**启动判定流程**（每次请求首先执行）：

1. `panel.bootstrap.php` 读取用户填写的 `APP_ROOT`：路径非法（相对路径、`realpath` 失败）→ 错误页，说明应填写的绝对路径与排查方法；
2. APP_ROOT 合法但缺少 `src/`、`vendor/` 等代码目录 → 错误页，提示把 `app/` 内容上传到该目录；
3. 代码齐全但未安装（无 `config.php` 或 `var/installed` 标记）→ 重定向 `install.php`；
4. 已安装 → 正常加载应用。

> 注意：APP_ROOT 的填写发生在文件系统（用户用编辑器 / 文件管理器修改 webroot 下 `panel.bootstrap.php`），安装向导**只检查、不提供 Web 修改入口**；未配置或路径错误时在启动器即报错并给出修复指引。

**Web 安装向导（install.php）分步流程**：

1. 环境检查：PHP 版本、`pdo_sqlite` / `openssl` / `curl` / `ZipArchive`、APP_ROOT 可写性、webroot 与 APP_ROOT 隔离、网络与上传能力探测；
2. 选择包来源（三种模式，环境探测决定可用项）：
   - **模式 A · 独立上传**：用户已把 `app/` 内容传到 APP_ROOT，向导仅做校验与初始化；
   - **模式 B · 服务端下载**：填写发布包 URL + SHA-256（必填），服务端下载到临时目录并校验后解压；
   - **模式 C · 页面上传**：浏览器上传 zip，向导校验后解压到 APP_ROOT（提示 `upload_max_filesize` / `post_max_size` 上限，必要时支持分卷或引导调整配置）；
3. 包校验：SHA-256 校验、zip-slip 防护（拒绝路径穿越、符号链接、超量文件）、结构校验（仅允许预期目录）、`manifest.json` 版本与 webroot 启动器兼容性检查；
4. 初始化：写入 `APP_ROOT/config.php`、创建 `var/`（SQLite、会话、备份、日志）与 `etc/` 状态目录、创建管理员账号；
5. 完成并锁定：清理临时文件，写入安装完成标记，再次访问 `install.php` 直接拒绝；重新安装需 CLI 或删除安装标记（有意的恢复动作）。

**闭环流程（免 SSH）**：① 在 webroot 的 `panel.bootstrap.php` 中填写 APP_ROOT（本机文件操作，非 Web）→ ② 上传 webroot 页面 → ③ 访问站点启动 `install.php`，**第一步检查 APP_ROOT**（未配置/路径错误即报错并给出修复指引）→ ④ 三选一获取安装包（独立放置 / 服务端下载 / 页面上传）→ ⑤ 填写设置 → ⑥ 完成安装进入登录页。同一机制可用于后续升级（备份 `var/` 与 `config.php` → 获取新包 → 校验 → 原子替换），升级纳入 P1。

**路径安全约束**：APP_ROOT 必须是绝对路径且不位于 webroot 之内（比较 `realpath` 前缀，防止配置被 Web 分发）；PHP 进程对该目录可写；`open_basedir` 限制时给出明确报错。

**CLI 的边界**：纯 Web 环境全流程可完成安装、初始化、改密、定时任务；SSH/CLI 仅在以下情况需要——Web 不可达时的恢复、Caddy 进程管理（启停）、安装插件（xcaddy）、systemd timer 等高级运维。

**可选发布形态**：Docker 镜像（php-fpm 或 php-cli + Caddy）与 FRAMPP 内嵌模块（`modules/control-panel`），二者都是同一份代码的封装，不改变上述部署约束。

---

## 10. 里程碑（草案）

| 里程碑 | 内容 | 验收 |
| --- | --- | --- |
| M1 骨架 | 项目结构、Vue+PHP 连通、Admin API 客户端、本地免认证 + CLI 密码重置、仪表盘、Caddyfile 只读/编辑/校验/热加载 | 表单改动实时生效；非法配置回滚并展示行号错误 |
| M2 站点管理 | 站点 CRUD（static/reverse_proxy/php）、启停、多域名、TLS 开关、证书查看+到期提醒 | 站点增删改不手改 Caddyfile 可完成 |
| M3 数据与日志 | 配置快照/回滚、站点备份、访问日志 tail 与安全分析、存储概览、审计、远程登录与设置页改密完善 | 误操作可一键回滚；攻击 IP / 扫描 URL 可识别 |
| M4 增强 | 负载均衡/健康检查、访问控制页、API+Token、导出导入、通知、定时备份、空间配额与告警 | FRAMPP 场景全部覆盖，独立部署文档完成 |
| P2 可选 | 按反馈挑选（多实例/统计/AI/终端等） | – |

---

## 11. 已定决策与遗留项

### 10.1 已定决策（v0.2）

1. 配置模型：Caddyfile 片段为唯一事实来源，表单覆盖 90%，高级编辑兜底。
2. 三层编辑粒度 + `/adapt`/`/load` 双校验 + “检查但不应用”。
3. 认证：本地免认证 + 单管理员；无多用户体系；API Token 保留（单管理员级）。
4. 证书：P0 查看 + 到期提醒；P1 导入/更换 + 还原自动证书。
5. 技术栈：PHP 8.1+（Slim 4 轻路由，vendor 随包）、Vue 3 + Vite + Naive UI、SQLite 元数据。
6. 项目名：`caddy-panel`（暂定，正式发布前可改）。
7. 运维能力（v0.3）：站点备份/恢复、日志安全分析、存储空间管理纳入 P1（详见 §8）。
8. 日志与安全（v0.4）：多日志设置（多输出/多粒度）与黑名单管理纳入 P1（详见 §8）。
9. 认证与配置细化（v0.5）：远程单管理员密码登录；密码三种重置途径（CLI / 配置 / 设置页）；`config.php` 承载 Caddy Admin API 地址与面板监听端口。

### 10.2 遗留项（不阻塞开发）

1. 正式发布前最终确认项目名与仓库命名空间。
2. Slim 4 若实现时被证明过重，可降级为极简自研路由（不改变接口契约）。
3. 证书导入 UI 细节（多证书批量、文件命名规范）。
4. 完全自定义模式与表单模式的边界提示文案。
5. i18n 文案维护方式。
6. 数据库备份是否纳入（FRAMPP 场景建议钩子方式，P2）。
7. 日志保留天数与攻击判定阈值默认值（当前草案：14 天 / 2GB、5 分钟 4xx≥20 等）。
8. 大目录空间扫描性能（增量扫描 + 缓存）。
9. 异地备份目标（SFTP / S3，P2）。
10. 黑名单有效期与默认封禁时长（建议 24 小时可配）。
11. 多输出日志目标（网络端点）的 UI 与告警细节。

---

## 12. 参考资料

- CaddyDash：<https://github.com/WJQSERVER-STUDIO/caddydash>，社区帖 <https://caddy.community/t/caddydash-a-lightweight-webui-to-manager-single-caddy-instance/31459>
- CaddyManager (MEVN)：<https://github.com/caddymanager/caddymanager>，社区帖 <https://caddy.community/t/caddymanager-0-0-1-a-web-ui-to-manage-multiple-caddy-instances/31396>
- CaddyManager (rhad00)：<https://github.com/rhad00/CaddyManager>
- CaddyProxyManager：<https://github.com/fuomag9/caddy-proxy-manager>
- CaddyUI：<https://github.com/X4Applegate/caddyui>，社区帖 <https://caddy.community/t/caddyui-v2-4-3-dashboard-polish-smarter-health-dots-multi-host-fixes/33664>
- Caddy Admin API：<https://caddyserver.com/docs/api>（`/load` 支持 `text/caddyfile`，`/adapt` 校验，`/config` 增量编辑，`/reverse_proxy/upstreams` 上游状态）
- nginx-ui：<https://github.com/0xJacky/nginx-ui>；nginxWebUI：<https://github.com/cym1102/nginxWebUI>
- Nginx Proxy Manager / CyberPanel / HestiaCP / aaPanel / Webmin-Virtualmin 官网与文档
