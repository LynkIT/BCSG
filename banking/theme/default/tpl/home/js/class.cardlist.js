class Cardlist {
	constructor() {
		var me = this;
		me.forms();
		me.drawList();
	}

	drawList() {
		//Ajax the card list and display it in the corresponding container.
		var me = this;
		$('#cardlist').html('<div class="loading"></div>');
		$.ajax({
			url:'index.php?home&act=getcards',
			success:function(html) {
				$('#cardlist').html(html);
			}
		});
	}

	forms() {
		//Setup the add new item form
		var me = this;

		$('#newcard').submit(function(e) {
			e.preventDefault();
			if($('#newcard input[type=submit]').hasClass('disabled')) return false;

			//Validate the form, all required fields must be present
			if(!me.validate()) return alert('You must complete all fields');

			//Prevent double clicking of the save button on valid form entries
			$('#newcard input[type=submit]').addClass('disabled');

			//POST the form data to the PHP processor
			$.ajax({
				url:'index.php?home&act=addcard',
				type:'post',
				data:$('#newcard').serialize(),
				success:function(json) {
					var ret = JSON.parse(json);
					if(ret.error===0) {
						me.drawList();

						//Clear the form
						$('#newcard input,#newcard select').not('input[type=submit]').val('');
						$('#newcard input[type=submit]').removeClass('disabled');
					} else {
						$('#newcard input[type=submit]').removeClass('disabled');
						alert(ret.msg);
					}
				}
			});
		});

		var file = new Files();
		$('#uploadfile_1').change(function() {
			file.validate();
		});

		//Setup the file uploader form
		$('#addcsv_1').submit(function(e) {
			e.preventDefault();
			if($('#addcsv input[type=submit]').hasClass('disabled')) return false;

			var upload = file.upload({
				form:this,
				success:function(json) {
					var ret = JSON.parse(json);
					if(ret.error===0) {
						me.drawList();
					} else {
						alert(ret.msg);
					}
				},
			});
		});
	}

	validate() {
		//Check that all fields are completed on hte form before submitting
		if(!$('input[name=bank]').val().length) return false;
		if(!$('input[name=cardnum]').val().length) return false;
		if(!$('select[name=month]').val().length) return false;
		if(!$('select[name=year]').val().length) return false;
		return true;
	}
}

$(document).ready(function() {
	var cards = new Cardlist();
});