# Setup the environment
```
kubectl apply -f custom_resource_definition.yaml
kubectl apply -f permissions.yaml
kubectl apply -f php-client.yaml
kubectl apply -f mywebserver.yaml
```

# Trigger the Control Loop
Just to keep things imple, I am not running the control loop script automcatically. You need to call it manually
Also, this script will automatically exit if there is an empty response from the API for more than 20 secs.

## Get the Pod name that is running our control loop script
```ubuntu@ip-172-31-6-125:~$ kubectl get pods
NAME                          READY   STATUS    RESTARTS   AGE
php-client-77d9c6c594-mhl7l   1/1     Running   0          92s
```

## Run the PHP control loop script
```
ubuntu@ip-172-31-6-125:~$ kubectl exec -ti php-client-77d9c6c594-mhl7l -- curl localhost/operator.php
----- Resource Version: 
INSIDE Last Resource Version: 17032
Type: ADDED; Name: webapp
----- EVENT TYPE: ADDED -----
SUCCESS: mws-webapp Deployment created.
----- Resource Version: 17033
INSIDE Last Resource Version: 17033
```

## Confirm the new deployment object is created
```
ubuntu@ip-172-31-6-125:~$ kubectl get deploy
NAME         READY   UP-TO-DATE   AVAILABLE   AGE
mws-webapp   2/2     2            2           2m16s
php-client   1/1     1            1           5m1s
ubuntu@ip-172-31-6-125:~$ kubectl get pods
NAME                          READY   STATUS    RESTARTS   AGE
mws-webapp-7c8948ffc-5vp7j    1/1     Running   0          2m19s
mws-webapp-7c8948ffc-gqnmk    1/1     Running   0          2m19s
php-client-77d9c6c594-mhl7l   1/1     Running   0          5m4s
```

# To Do
+ Create a service object to browse the deployment
+ Optimize the event list from the watch

# Disclaimer
This script is absolutely NOT intended to be used in any production environment. It is just to understand K8s Operators - use it as learning material only

# Inspired By (with gratitude)
+ https://github.com/travisghansen/kubernetes-client-php
+ https://www.baeldung.com/java-kubernetes-watch
+ YAML to JSON converter: https://onlineyamltools.com/convert-yaml-to-json