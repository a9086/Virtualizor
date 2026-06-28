<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

function virtualizor_oneclick_boot_config()
{
    return [
        "name" => "Virtualizor一键开机",
        "description" => "批量查询 WHMCS 服务，清空旧 Virtualizor 绑定信息，并对勾选服务执行开通命令。",
        "version" => "1.2.0",
        "author" => "Codex",
        "fields" => [],
    ];
}

function virtualizor_oneclick_boot_activate()
{
    return ["status" => "success", "description" => "Virtualizor一键开机已启用。"];
}

function virtualizor_oneclick_boot_deactivate()
{
    return ["status" => "success", "description" => "Virtualizor一键开机已停用。"];
}

function virtualizor_oneclick_boot_output($vars)
{
    $action = $_POST["oneclick_action"] ?? "";
    $productId = (int)($_POST["product_id"] ?? $_GET["product_id"] ?? 0);
    $statusFilter = (string)($_POST["status_filter"] ?? $_GET["status_filter"] ?? "Active,Suspended,Pending");
    $message = "";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        check_token("WHMCS.admin.default");
        if ($action === "boot_selected") {
            $message = virtualizor_oneclick_boot_selected(
                $productId,
                $_POST["selected_services"] ?? [],
                !empty($_POST["clear_links"]),
                !empty($_POST["run_module_create"]),
                !empty($_POST["confirm_execute"])
            );
        }
    }

    $products = Capsule::table("tblproducts")
        ->orderBy("gid")
        ->orderBy("name")
        ->get(["id", "gid", "name", "servertype"]);
    $services = $productId > 0 ? virtualizor_oneclick_get_services($productId, $statusFilter) : [];
    $linkFields = $productId > 0 ? virtualizor_oneclick_get_link_fields($productId) : [];

    echo '<div class="container-fluid">';
    echo '<h2>Virtualizor一键开机</h2>';
    echo '<p>请选择产品后查询服务，只会处理你勾选的服务。插件不会删除订单、不会删除客户、不会主动终止 VPS。</p>';
    echo '<div class="alert alert-warning">提示：如果 Virtualizor 模块本身报错，插件会捕获并显示错误，不会再让 WHMCS 后台直接 Oops。</div>';

    if ($message) {
        echo '<div class="alert alert-info">' . $message . '</div>';
    }

    echo '<form method="get" class="form-inline" style="margin-bottom:20px;">';
    echo '<input type="hidden" name="module" value="virtualizor_oneclick_boot">';
    echo '<div class="form-group" style="margin-right:12px;"><label style="margin-right:6px;">产品</label>';
    echo '<select class="form-control" name="product_id">';
    echo '<option value="0">请选择产品</option>';
    foreach ($products as $product) {
        $selected = ((int)$product->id === $productId) ? ' selected' : '';
        $label = "#" . $product->id . " - " . $product->name . " [" . $product->servertype . "]";
        echo '<option value="' . (int)$product->id . '"' . $selected . '>' . htmlspecialchars($label, ENT_QUOTES, "UTF-8") . '</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group" style="margin-right:12px;"><label style="margin-right:6px;">状态</label>';
    echo '<input class="form-control" name="status_filter" value="' . htmlspecialchars($statusFilter, ENT_QUOTES, "UTF-8") . '" style="width:260px;">';
    echo '</div>';
    echo '<button class="btn btn-primary" type="submit">查询服务</button>';
    echo '</form>';

    if ($productId <= 0) {
        echo '<div class="alert alert-warning">请先选择一个产品。</div>';
        echo '</div>';
        return;
    }

    echo '<div class="alert alert-warning">';
    echo '已识别到 ' . count($linkFields) . ' 个疑似 Virtualizor 绑定字段。建议先只勾选一台旧服务测试。';
    echo '</div>';

    echo '<form method="post">';
    echo generate_token("form");
    echo '<input type="hidden" name="oneclick_action" value="boot_selected">';
    echo '<input type="hidden" name="product_id" value="' . (int)$productId . '">';
    echo '<input type="hidden" name="status_filter" value="' . htmlspecialchars($statusFilter, ENT_QUOTES, "UTF-8") . '">';

    echo '<div style="margin-bottom:12px;">';
    echo '<button type="button" class="btn btn-default btn-sm" onclick="virtualizorOneclickSetChecked(true)">全选</button> ';
    echo '<button type="button" class="btn btn-default btn-sm" onclick="virtualizorOneclickSetChecked(false)">取消全选</button>';
    echo '</div>';

    echo '<table class="table table-striped table-hover table-condensed">';
    echo '<thead><tr>';
    echo '<th style="width:40px;"><input type="checkbox" onclick="virtualizorOneclickSetChecked(this.checked)"></th>';
    echo '<th>服务ID</th><th>客户ID</th><th>客户名</th><th>产品/域名</th><th>状态</th><th>到期时间</th><th>Virtualizor绑定字段</th>';
    echo '</tr></thead><tbody>';

    if (count($services) === 0) {
        echo '<tr><td colspan="8">没有查询到符合条件的服务。</td></tr>';
    }

    foreach ($services as $service) {
        $values = virtualizor_oneclick_get_link_values($service->id, $linkFields);
        echo '<tr>';
        echo '<td><input class="oneclick-service-checkbox" type="checkbox" name="selected_services[]" value="' . (int)$service->id . '"></td>';
        echo '<td><a href="clientsservices.php?userid=' . (int)$service->userid . '&id=' . (int)$service->id . '" target="_blank">#' . (int)$service->id . '</a></td>';
        echo '<td><a href="clientssummary.php?userid=' . (int)$service->userid . '" target="_blank">#' . (int)$service->userid . '</a></td>';
        echo '<td>' . htmlspecialchars(trim($service->firstname . " " . $service->lastname), ENT_QUOTES, "UTF-8") . '</td>';
        echo '<td>' . htmlspecialchars($service->product_name . " / " . $service->domain, ENT_QUOTES, "UTF-8") . '</td>';
        echo '<td>' . htmlspecialchars($service->domainstatus, ENT_QUOTES, "UTF-8") . '</td>';
        echo '<td>' . htmlspecialchars($service->nextduedate, ENT_QUOTES, "UTF-8") . '</td>';
        echo '<td>' . $values . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<div class="panel panel-default"><div class="panel-body">';
    echo '<label><input type="checkbox" name="clear_links" value="1" checked> 清空勾选服务的旧 Virtualizor 绑定字段</label><br>';
    echo '<label><input type="checkbox" name="run_module_create" value="1"> 清空后执行 WHMCS 开通命令</label><br>';
    echo '<label><input type="checkbox" name="confirm_execute" value="1"> 我确认只处理上方已勾选的服务</label><br><br>';
    echo '<button class="btn btn-danger" type="submit">执行一键开机</button>';
    echo '</div></div>';
    echo '</form>';

    echo '<script>
function virtualizorOneclickSetChecked(checked) {
    var boxes = document.querySelectorAll(".oneclick-service-checkbox");
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = checked;
    }
}
</script>';
    echo '</div>';
}

function virtualizor_oneclick_get_services($productId, $statusFilter)
{
    $statuses = array_filter(array_map("trim", explode(",", $statusFilter)));
    if (!$statuses) {
        $statuses = ["Active", "Suspended", "Pending"];
    }

    return Capsule::table("tblhosting as h")
        ->join("tblclients as c", "c.id", "=", "h.userid")
        ->join("tblproducts as p", "p.id", "=", "h.packageid")
        ->where("h.packageid", $productId)
        ->whereIn("h.domainstatus", $statuses)
        ->orderBy("h.id", "desc")
        ->get([
            "h.id",
            "h.userid",
            "h.domain",
            "h.domainstatus",
            "h.nextduedate",
            "c.firstname",
            "c.lastname",
            "p.name as product_name",
        ]);
}

function virtualizor_oneclick_get_link_fields($productId)
{
    return Capsule::table("tblcustomfields")
        ->where("type", "product")
        ->where("relid", $productId)
        ->where(function ($q) {
            $q->where("fieldname", "like", "%vps%")
                ->orWhere("fieldname", "like", "%virtualizor%")
                ->orWhere("fieldname", "like", "%vid%")
                ->orWhere("fieldname", "like", "%vmid%");
        })
        ->orderBy("id")
        ->get(["id", "fieldname"]);
}

function virtualizor_oneclick_get_link_values($serviceId, $linkFields)
{
    if (count($linkFields) === 0) {
        return '<span class="text-muted">未识别到绑定字段</span>';
    }

    $fieldIds = [];
    foreach ($linkFields as $field) {
        $fieldIds[] = (int)$field->id;
    }

    $values = Capsule::table("tblcustomfieldsvalues")
        ->where("relid", $serviceId)
        ->whereIn("fieldid", $fieldIds)
        ->pluck("value", "fieldid")
        ->all();

    $parts = [];
    foreach ($linkFields as $field) {
        $value = $values[$field->id] ?? "";
        $label = htmlspecialchars($field->fieldname, ENT_QUOTES, "UTF-8");
        $displayValue = $value === "" ? '<span class="text-muted">空</span>' : '<code>' . htmlspecialchars($value, ENT_QUOTES, "UTF-8") . '</code>';
        $parts[] = $label . ': ' . $displayValue;
    }
    return implode("<br>", $parts);
}

function virtualizor_oneclick_boot_selected($productId, $selectedServices, $clearLinks, $runModuleCreate, $confirmed)
{
    $serviceIds = array_values(array_unique(array_map("intval", (array)$selectedServices)));
    $serviceIds = array_filter($serviceIds, function ($id) {
        return $id > 0;
    });

    if ($productId <= 0) {
        return "请先选择产品。";
    }
    if (!$serviceIds) {
        return "请至少勾选一个服务。";
    }
    if (!$confirmed) {
        return "请勾选确认框后再执行。";
    }
    if (!$clearLinks && !$runModuleCreate) {
        return "请至少选择一个执行动作。";
    }

    $services = Capsule::table("tblhosting")
        ->where("packageid", $productId)
        ->whereIn("id", $serviceIds)
        ->orderBy("id")
        ->get(["id", "userid", "domainstatus"]);

    if (count($services) === 0) {
        return "没有找到属于该产品的已勾选服务。";
    }

    $linkFields = virtualizor_oneclick_get_link_fields($productId);
    $fieldIds = [];
    foreach ($linkFields as $field) {
        $fieldIds[] = (int)$field->id;
    }

    $lines = [];
    foreach ($services as $service) {
        if ($clearLinks && $fieldIds) {
            Capsule::table("tblcustomfieldsvalues")
                ->where("relid", $service->id)
                ->whereIn("fieldid", $fieldIds)
                ->update(["value" => ""]);
            logActivity("Virtualizor一键开机：已清空服务 #" . $service->id . " 的旧绑定字段");
        }

        if ($runModuleCreate) {
            try {
                $result = localAPI("ModuleCreate", ["accountid" => $service->id]);
                if (($result["result"] ?? "") === "success") {
                    $lines[] = "服务 #" . $service->id . "：开通成功";
                    logActivity("Virtualizor一键开机：服务 #" . $service->id . " 开通成功");
                } else {
                    $error = $result["message"] ?? "未知错误";
                    $lines[] = "服务 #" . $service->id . "：开通失败 - " . $error;
                    logActivity("Virtualizor一键开机：服务 #" . $service->id . " 开通失败 - " . $error);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $lines[] = "服务 #" . $service->id . "：Virtualizor 模块异常 - " . $error;
                logActivity("Virtualizor一键开机：服务 #" . $service->id . " 模块异常 - " . $error);
            }
        } else {
            $lines[] = "服务 #" . $service->id . "：已清空旧绑定，未执行开通";
        }
    }

    return implode("<br>", array_map(function ($line) {
        return htmlspecialchars($line, ENT_QUOTES, "UTF-8");
    }, $lines));
}
