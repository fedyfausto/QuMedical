
$(document).ready(function(){
	var system = new System();
	system.taskList(function(data){
		console.log(data);
	});
});