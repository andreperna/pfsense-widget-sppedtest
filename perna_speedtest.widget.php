<?php

require_once("guiconfig.inc");


function list_all_interfaces()
{
    $ifdescrs = get_configured_interface_with_descr();
    $arr_interfaces = array();
    foreach ($ifdescrs as $ifdescr => $ifname) {
        $ifinfo = get_interface_info($ifdescr);
        if ($ifinfo['pppoelink'] || $ifinfo['pptplink'] || $ifinfo['l2tplink']) {
            /* PPP link (non-cell) - looks like a modem */
            $typeicon = 'hdd-o';
        } else if ($ifinfo['ppplink']) {
            /* PPP Link (usually cellular) */
            $typeicon = 'signal';
        } else if (is_interface_wireless($ifdescr)) {
            /* Wi-Fi interface (hostap/client/etc) */
            $typeicon = 'wifi';
        } else {
            /* Wired/other interface. */
            $typeicon = 'sitemap';
        }
        $arr_interfaces[$ifdescr]["name"] = $ifname;
        $arr_interfaces[$ifdescr]["icon"] = $typeicon;
        $arr_interfaces[$ifdescr]["ip"] = $ifinfo["ipaddr"];
    }
    return $arr_interfaces;
}

function list_wan_interfaces()
{
    $arr_all_interfaces = list_all_interfaces();
    $arr_wan_interfaces = array_filter($arr_all_interfaces, function ($item) {
        return $item["icon"] == "hdd-o";
    });
    return $arr_wan_interfaces;
}

function speedtest($source_ip)
{
    $results = shell_exec("speedtest --source $source_ip --json");
    return $results;
}

function speedtest_filtered($source_ip)
{
    $result = speedtest($source_ip);
    $decode_result = json_decode($result);
    $response = array(
        "download" => $decode_result->download,
        "upload" => $decode_result->upload,
        "ping" => $decode_result->ping,
        "isp" => $decode_result->client->isp,
    );
    return $response;
}

function list_wan_interfaces_with_speedtest()
{
    $interfaces = list_wan_interfaces();

    function add_speedtest($item)
    {
        $source_ip = $item["ip"];
        $speedtest_result = speedtest_filtered($source_ip);
        return [...$item, ...$speedtest_result];
    }

    $response = array_map('add_speedtest', $interfaces);
    return $response;
}

function fake_response()
{
    $response = array(
        "wan" => array(
            "name" => "WAN1",
            "icon" => "hdd-o",
            "ip" => "177.87.234.45",
            "download" => 87773060.662143,
            "upload" => 94073441.249458,
            "ping" => 6.855,
            "isp" => "Monte Alto Net Ltda",
        ),
        "opt1" => array(
            "name" => "WAN2",
            "icon" => "hdd-o",
            "ip" => "45.176.25.13",
            "download" => 80278121.704381,
            "upload" => 88449338.683878,
            "ping" => 12.24,
            "isp" => "Cidade Sonho Telecom Eireli - Epp",
        )
    );

    return $response;
    // echo "<pre>";
    // print_r($response);
    // echo "</pre>";
    // var_dump($response);
}


// execute speed test and return json informations
$update = $_GET["update"];
if ($update) {
    $response = list_wan_interfaces_with_speedtest();
    // $response = fake_response();
    header('Content-Type: application/json');
    echo json_encode($response, JSON_FORCE_OBJECT);
    return;
}


// create initial speed table (without values)
$wans = list_wan_interfaces();
echo "<table id=\"speed_table\" class=\"table table-striped table-hover table-condensed\">";
foreach ($wans as $wan) {
    $interface = $wan['name'];
    echo 
    "
    <tr>
    <th colspan=\"3\">{$interface}</th>
    </tr>
    <tr>
    <td>Ping: <b>XXX.XX</b><small> ms</small></td>
    <td>Download: <b>XXX.XX</b><small> Mbps</small></td>
    <td>Upload: <b>XXX.XX</b><small> Mbps</small></td>
    </tr>
    ";
}
echo 
"
</table>
<div style=\"padding: 0.5rem 1rem; float: right; text-align: center;\">
    <a id=\"btn_updspeed\" onclick=\"update()\" class=\"fa fa-refresh\"></a>
</div>
";

?>


<script type="text/javascript">

    // get DOM elements
    const speed_table = document.getElementById("speed_table")
    const btn_updspeed = document.getElementById("btn_updspeed")

    // update speed table
    async function update() {
        // add button animate
        btn_updspeed.classList.add("fa-spin")

        const response = await fetch('/widgets/widgets/perna2.widget.php?update=true')
        const json = await response.json()
        console.log(json)

        // create table contents
        const arr_table = []
        for (const item in json) {
            const string_item = `
            <tr>
            <th colspan="3">${json[item]["isp"]}</th>
            </tr>
            <tr>
            <td>Ping: <b>${json[item]["ping"].toFixed(2)}</b><small> ms</small></td>
            <td>Download: <b>${(json[item]["download"] / 1000000).toFixed(2)}</b><small> Mbps</small></td>
            <td>Upload: <b>${(json[item]["upload"] / 1000000).toFixed(2)}</b><small> Mbps</small></td>
            </tr>`
            arr_table.push(string_item)
        }

        // insert contents in table
        speed_table.innerHTML = arr_table.join("")

        // remove button animate
        btn_updspeed.classList.remove("fa-spin")

    }

</script>