
  var timer;

  // The function to refresh the progress bar.
  function refreshProgress() {
    var d = new Date();
var n = d.getSeconds();
console.log('refresh now:' + n);
    // We use Ajax again to check the progress by calling the checker script.
    // Also pass the session id to read the file because the file which storing the progress is placed in a file per session.
    // If the call was success, display the progress bar.
    jQuery.ajax({
      url: "checker.php?file=<?php echo session_id() ?>",
      success:function(data){
        jQuery("#progress").html('<div class="bar" style="width:' + data.percent + '%"></div>');
        jQuery("#message").html(data.message);
        // If the process is completed, we should stop the checking process.
        if (data.percent == 100) {
          window.clearInterval(timer);
          timer = window.setInterval(completed, 1000);
        }
      }
    });
  }

  function completed() {
    jQuery("#message").html("Completed");
    window.clearInterval(timer);
  }

  // When the document is ready
  jQuery(document).ready(function(){
    // Trigger the process in web server.
    jQuery.ajax({url: "process.php"});
    // Refresh the progress bar every 1 second.
    timer = window.setInterval(refreshProgress, 1000);
  });
