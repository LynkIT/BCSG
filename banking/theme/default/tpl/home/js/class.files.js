class Files {
	constructor() {
		var me = this;
		me.id = 1;			//Form ID - useful for multiple uploaders
		me.mask	= ['csv'];	//Which file types are allowed
		me.limit = 1000000;
	}

	validate() {
		var me = this;
		var ext 			= $('#uploadfile_' + me.id).val().split('.').pop().toLowerCase();
		var filename		= $('#uploadfile_' + me.id)[0].files[0].name;
	
		if($.inArray(ext, me.mask) == -1) {
			$('#filesubmit_' + me.id).addClass('disabled').prop('disabled', true);
			$('#filename_' + me.id).html("This file type isnt supported");
			return false;
		} else if($('#uploadfile_'+me.id)[0].files[0].size > me.limit) {
			$('#filesubmit_'+me.id).addClass('disabled').prop('disabled', true);
			$('#filename_'+me.id).html("The file is too large");
			return false;
		} else {
			$('#filesubmit_'+me.id).removeClass('disabled').removeAttr('disabled');
			$('#filename_'+me.id).html(filename);
			return true;
		}
	}

	upload(params) {
		var me = this;
		var bar			= $('#status_' + me.id + ' .bar');
		var percent		= $('#status_' + me.id + ' .percent');
		var status		= $('#status_' + me.id);
		
		$.ajax({
			xhr:function() {
				var xhr = new window.XMLHttpRequest();
				xhr.upload.addEventListener("progress", function(evt) {
					if (evt.lengthComputable) {
						var percentComplete = Math.round((evt.loaded / evt.total)*100) + '%';
						percent.html(percentComplete);
					}
				}, false);
				return xhr;
			},
			url:'index.php?home&act=uploadcsv',
			type:'POST',
			data:new FormData(params.form),
			processData:false,
			contentType:false,
			success:function(result) {
				var percentVal = '100%';
				bar.width(percentVal)
				percent.html(percentVal);
				$('#tfileid').val($('#tfileid').val() + ',' + result);
				if(params.success) params.success(result);
			},
			error: function() {
				return false;
			}
		});
	}
}