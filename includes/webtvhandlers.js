function webtvuploadSuccess(file, serverData) {
	try {
		if (serverData == "sucess") {
			document.getElementById('eleList').innerHTML = '<span style="color: #009015;">uploaded</span><span style="color: #6F6F6F;"> ' + file.name + ' '+ file.size + '</span>';
			document.getElementById('uploaddiv').innerHTML = 'Video Uploaded';
		} else {
			document.getElementById('webtv-status-upload').innerHTML = '<span style="color: #FF0000;">error</span><span style="color: #6F6F6F;"> ' + serverData + '</span>';
		}

	} catch (ex) {
		this.debug(ex);
	}
}

function webtvfileDialogComplete(numFilesSelected, numFilesQueued) {
	try {
		if (numFilesSelected > 0) {
			//document.getElementById(this.customSettings.cancelButtonId).disabled = false;
		}
		
		/* I want auto start the upload and I can do that here */
		this.startUpload();
	} catch (ex)  {
        this.debug(ex);
	}
}

function webtvuploadStart(file) {
	try {
		document.getElementById('webtv-status-upload').innerHTML = "Uploading";
		/*var progress = new FileProgress(file, this.customSettings.progressTarget);
		progress.setStatus("Uploading...");
		progress.toggleCancel(true, this);*/
	}
	catch (ex) {}
	
	return true;
}

function webtvuploadProgress(file, bytesLoaded, bytesTotal) {
	try {
		var percent = Math.ceil((bytesLoaded / bytesTotal) * 100);
		var s = ['bytes', 'kb', 'MB', 'GB', 'TB', 'PB'];
	    var e = Math.floor(Math.log(bytesTotal)/Math.log(1024));
    	var sizeTotal = (bytesTotal/Math.pow(1024, Math.floor(e))).toFixed(2)+" "+s[e];

		document.getElementById('webtv-status-upload').innerHTML = percent + "% of "+ sizeTotal; 

		/*var progress = new FileProgress(file, this.customSettings.progressTarget);
		progress.setProgress(percent);
		progress.setStatus("Uploading...");*/
	} catch (ex) {
		this.debug(ex);
	}
}

function webtvuploadError(file, errorCode, message) {
	try {
		
		switch (errorCode) {
		case SWFUpload.UPLOAD_ERROR.HTTP_ERROR:
			document.getElementById('webtv-status-upload').innerHTML = "Upload Error: " + message;
			this.debug("Error Code: HTTP Error, File name: " + file.name + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.UPLOAD_FAILED:
			document.getElementById('webtv-status-upload').innerHTML = "Upload Failed.";
			this.debug("Error Code: Upload Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.IO_ERROR:
			document.getElementById('webtv-status-upload').innerHTML = "Server (IO) Error";
			this.debug("Error Code: IO Error, File name: " + file.name + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.SECURITY_ERROR:
			document.getElementById('webtv-status-upload').innerHTML = "Security Error";
			this.debug("Error Code: Security Error, File name: " + file.name + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.UPLOAD_LIMIT_EXCEEDED:
			document.getElementById('webtv-status-upload').innerHTML = "Upload limit exceeded.";
			this.debug("Error Code: Upload Limit Exceeded, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.FILE_VALIDATION_FAILED:
			document.getElementById('webtv-status-upload').innerHTML = "Failed Validation.  Upload skipped.";
			this.debug("Error Code: File Validation Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
			break;
		case SWFUpload.UPLOAD_ERROR.FILE_CANCELLED:
			// If there aren't any files left (they were all cancelled) disable the cancel button
			if (this.getStats().files_queued === 0) {
				//document.getElementById(this.customSettings.cancelButtonId).disabled = true;
			}
			document.getElementById('webtv-status-upload').innerHTML = "Cancelled";
			//progress.setCancelled();
			break;
		case SWFUpload.UPLOAD_ERROR.UPLOAD_STOPPED:
			document.getElementById('webtv-status-upload').innerHTML = "Stopped";
			break;
		default:
			document.getElementById('webtv-status-upload').innerHTML = "Unhandled Error: " + errorCode;
			this.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
			break;
		}
	} catch (ex) {
        this.debug(ex);
    }
}

function submit_form_upload() {
	document.forms['post'].submit();
}

function webtvuploadComplete(file) {
	if (this.getStats().files_queued === 0) {
		//document.getElementById('uploaddiv').innerHTML = '<span id="spanButtonPlaceHolder"><a href="#webtv-status-info" onclick="submit_form_upload()">Retry Upload</a></span>';
		//document.getElementById(this.customSettings.cancelButtonId).disabled = true;
	}
}
