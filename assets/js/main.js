
$(document).ready(function(){
	var system = new System();
	system.getDiskSpace(function(data){
		console.log(data);
	});
});