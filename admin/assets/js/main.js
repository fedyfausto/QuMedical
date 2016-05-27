
$(document).ready(function(){
	var system = new System();
	system.taskList(function(data){
		console.log(data);
	});

	system.ramUsage(function(data){
		console.log(data);
	});

	system.getBandwidth(function(data){
		console.log(data);
	},null);

	system.getServerLoad(function(data){
		console.log(data);
	});

	system.getDiskSpace(function(data){
		console.log(data);
	});
});