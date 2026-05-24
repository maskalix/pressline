# Example Plugin

Reference plugin for PressLine demonstrating the plugin SDK.

## Folder structure

```
example/
├── plugin.json    Manifest (id, name, version, hooks, permissions)
├── plugin.php     Entry point — registers hooks via PL_Plugin
├── page.php       Custom admin page reachable from the sidebar
└── readme.md      This file
```

## What this plugin does

| Hook                  | Behavior                                              |
|-----------------------|-------------------------------------------------------|
| `article.afterSave`   | Logs the saved article id+name to `error_log`         |
| `media.afterUpload`   | Logs the filename to `error_log`                      |
| `admin.menu` filter   | Adds an "Example" item to the admin sidebar           |
| `admin.dashboardCards`| Adds a card to the dashboard                          |

## Activating

1. Open **Pluginy** in the admin sidebar (admin-only).
2. Find "Example Plugin" in the list.
3. Click **Aktivovat**.

## Writing your own plugin

Copy this folder, rename to your plugin id, edit `plugin.json` and `plugin.php`.
The full hook list and SDK docs are on the **Pluginy** page itself.
