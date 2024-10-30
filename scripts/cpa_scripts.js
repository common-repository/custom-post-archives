jQuery(document).ready(function($){
	$("#cpa_deactivate").click(function(){
		var remove_data = confirm("Delete all Custom Post Archive Data from database as well?");
		if(remove_data){
			window.location = $(this).attr('href')+"&delete_data=true";
		}else{
			window.location = $(this).attr('href');
		}
		return false;
	});
});