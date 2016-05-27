
$(document).ready(function(){
	var system = new System();
	system.taskList(function(data){
		console.log(data);
		var array = data.data;

		var columns=null;
		var objects = [];
		$.each(array, function( index, object ) {
			var realObject = object.oData;
			if(columns === null){
				columns = Object.keys(realObject);
			}
			objects.push(realObject);

		});

		$("#table-task").bootstrapTable({
			columns: columns,
			data: objects
		});
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