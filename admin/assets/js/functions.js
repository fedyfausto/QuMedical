function System() {
	this.url_request="assets/php/request.php";
	this.ramUsage = function(callback){request({action:"taskList"},callback);};
	this.netInterfaces= function(callback){request({action:"netInterfaces"},callback);};
	this.getBandwidth= function(callback,interface){request({action:"getBandwidth",int:interface},callback);}
	this.getServerLoad= function(callback){request({action:"getServerLoad"},callback);};
	this.taskList = function(callback){request({action:"taskList"},callback);};
	this.abortTask = function(callback,rid){request({action:"abortTask",rid:rid},callback);};
	this.deleteTask = function(callback,rid){request({action:"deleteList",rid:rid},callback);};
	this.countTask = function(callback,status){request({action:"countTask",status:status},callback);};
	this.getDiskSpace = function(callback){request({action:"getDiskSpace"},callback);};
};



function request(data,callback){
	var url_request="assets/php/request.php";
	$.post(url_request,data,function(data){
		if(callback!==undefined && callback!==null){
			callback(data);
		}
	},'json');
};