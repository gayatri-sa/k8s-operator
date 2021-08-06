<?php
// This is the script that you need to call to start the Control Loop

ob_implicit_flush(true);
include_once('k8s_client.php');

// Connect to the K8s Cluster.
$myop = new K8sClient();
// print_r($myop->getList());

// Start the control loop
$resourceVersion = '';
do {
    ob_start();
    echo '----- Resource Version: ' . $resourceVersion . "\n";
    ob_flush();
    flush();
    $events = $myop->watchObject($resourceVersion);
    if (!empty($events) && is_array($events)) {
        $latestResourceVersion = $events[sizeof($events)-1]['object']['metadata']['resourceVersion'];
        if ($resourceVersion >= $latestResourceVersion) {
            break;
        }
        $resourceVersion = $latestResourceVersion;
    } else {
        $events = [];
    }
    foreach ($events as $event) {
        // echo "Type: " . $event['type'] . '; Name: ' . $event['object']['metadata']['name'] . '; Spec: ' . print_r($event['object']['spec'], 1) . "\n";
        echo "Type: " . $event['type'] . '; Name: ' . $event['object']['metadata']['name'] . "\n";
        
        switch ($event['type']) {
            case 'ADDED':
                echo '----- EVENT TYPE: ADDED -----' . "\n";
                $search  = array('<NAME>', '<REPLICAS>', '<IMAGE>', '<PORT>');
                $replace = array('mws-'.$event['object']['metadata']['name'], $event['object']['spec']['replicas'], $event['object']['spec']['image'], $event['object']['spec']['port']);
                $deployment = str_replace($search, $replace, file_get_contents('jsonfiles/deployment.json'));
                
                // print_r($deployment);
                // ob_flush();
                // flush();
                
                $response = $myop->createDeployment($deployment);
                if ($response['status'] == 'Failure') {
                    echo 'ERROR: ' . $response['reason'] . ' - ' . $response['message'] . "\n";
                } else {
                    echo 'SUCCESS: ' . $response['metadata']['name'] . ' ' . $response['kind'] . ' created.' . "\n";
                    // print_r($response);
                }
                ob_flush();
                flush();
                break;
            case 'DELETED':
                echo '----- EVENT TYPE: DELETED -----' . "\n";
                $response = $myop->deleteDeployment('mws-'.$event['object']['metadata']['name']);
                if ($response['status'] == 'Failure') {
                    $myop->printStreamContext();
                    echo 'ERROR: ' . $response['reason'] . ' - ' . $response['message'] . "\n";
                } else {
                    echo 'SUCCESS: ' . $response['details']['name'] . ' ' . $response['details']['kind'] . ' deleted.' . "\n";
                }
                // print_r($response);
                ob_flush();
                flush();
                break;
        }
    }
    ob_end_flush();
    
} while (!empty($events));
ob_implicit_flush(false);