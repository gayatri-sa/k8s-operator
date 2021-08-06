<?php
// Connect to the cluster to make API calls

include_once ("stream.php");
class K8sClient {
    private $token = '';
    const APISERVER = 'https://kubernetes'; // kubernetes service name that will work only within a container of a pod
    
    // Assumes you have connected a Service Account to the Pod with the required permissions
    function __construct() {
        $this->token = file_get_contents('/run/secrets/kubernetes.io/serviceaccount/token');
        $this->stream = new Stream($this->token);
    }
    
    // Call the K8s cluster
    private function callCluster($endpoint, $poll=false, $json=null, $delete=false) {
        $service_url = self::APISERVER . $endpoint;
        
        if ($poll === true) {
            $response = $this->stream->getPollResponse($service_url, []);
        } else {
            $response = $this->stream->getResponse($service_url, [], $json, $delete);    
        }
        return $response;
    }
    
    // list of objects of the type mywebservers in any namespace
    public function getList() {
        $response   = $this->callCluster('/apis/k8sobjects.gsa.com/v2/mywebservers');
        $totalitems = sizeof($response['items']);
        $lastitem   = $response['items'][($totalitems-1)];
        
         // watch will return objects whose resource version is greater than this.
         // just to ensure we get this last object too in our watch I am watching
         // for a version 1 less than the last version
         // THIS IS NEEDED BECAUSE WE ARE MANUALLY TRIGGERING THE WATCH. IT SHOULD 
         // HAPPEN AUTOMATICALLY IN A LOOP
        $this->lastResourceVersion = $lastitem['metadata']['resourceVersion'];
        if ($this->lastResourceVersion > 0) {
            $this->lastResourceVersion = $this->lastResourceVersion - 1;
        }
        return $response['items'];
    }
    
    // watch mywebserver object in the default namespace
    public function watchObject($resourceVersion='') {
        $this->setLastResourceVersion($resourceVersion);
        
        echo 'INSIDE Last Resource Version: '  . $this->lastResourceVersion . "\n";
        $objlist = $this->callCluster('/apis/k8sobjects.gsa.com/v2/namespaces/default/mywebservers?watch=1&resourceVersion=' . $this->lastResourceVersion, true);
        return $objlist;
        
    }
    
    // create a deployment object
    function createDeployment($json) {
        return $this->callCluster('/apis/apps/v1/namespaces/default/deployments', false, $json);
    }
    
    // delete a deployment object
    function deleteDeployment($name) {
        return $this->callCluster('/apis/apps/v1/namespaces/default/deployments/' . $name, false, null, true);
    }
    
    // set the latest resourceVersion
    function setLastResourceVersion($resourceVersion) {
        if (isset($resourceVersion) && !empty($resourceVersion)) {
            $this->lastResourceVersion = $resourceVersion;
        }
        
        if (empty($this->lastResourceVersion)) {
            $this->getList();
        }
        
    }
    
    // just for debugging
    function printStreamContext() {
        $this->stream->printStreamContext();
    }
}