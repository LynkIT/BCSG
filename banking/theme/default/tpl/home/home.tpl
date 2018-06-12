{include siteheader.tpl}
{bring home/js/class.files.js}
{bring home/js/class.cardlist.js}
{bring home/css/cardlist.css}
<div id="cardlist" class="cardlist">
	<div class="loading"></div>
{include home/cardlist.tpl}
</div>



<form id="newcard" class="newcard">
	<h3>Add a new card</h3>
	<label for="bank">Bank Name</label>
	<input type="text" name="bank" size="25" maxlength="50" tabindex="1" /><br />

	<label for="cardnum">Card Number</label>
	<input type="text" name="cardnum" size="20" maxlength="19" tabindex="2" /><br />

	<label for="month">Expiry</label>
	<span class="dropdown light" style="margin-top:5px">
		<select name="month" tabindex="3">
			<option value=""></option>
			<option value="1">Jan</option>
			<option value="2">Feb</option>
			<option value="3">Mar</option>
			<option value="4">Apr</option>
			<option value="5">May</option>
			<option value="6">Jun</option>
			<option value="7">Jul</option>
			<option value="8">Aug</option>
			<option value="9">Sep</option>
			<option value="10">Oct</option>
			<option value="11">Nov</option>
			<option value="12">Dec</option>
		</select>
	</span>
	<span class="dropdown light" style="margin:5px 0 0 5px">
		<select name="year" tabindex="4">
			<option value=""></option>
			{foreach $yrs as $y}
			<option value="{$y}">{$y}</option>
			{/foreach}
		</select>
	</span>
	<div class="clearfloat"></div>
	<label>&nbsp;</label>
	<input type="submit" class="submit" value="Save" tabindex="5" />
	<div class="clearfloat"></div>
</form>
<form id="addcsv_1" class="addcsv" enctype="multipart/form-data">
	<h3>Upload a CSV file</h3>

	<label>Uploaded</label>
	<div class="progress">
		<div class="bar"></div>
		<div class="percent">0%</div>
	</div>
	<label>Select File</label>
	<input type="file" name="uploadfile_1" id="uploadfile_1" />
	<div id="filename_1" class="filename">Click the browse button to attach a file.</div>

	<div class="clearfloat"></div>
	<label>&nbsp;</label>
	<input type="submit" class="submit" id="filesubmit_1" value="Upload" tabindex="5" />
	<div class="clearfloat"></div>
</form>


{include sitefooter.tpl}