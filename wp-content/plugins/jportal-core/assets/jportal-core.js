(function($){
  'use strict';
  function notice(message){
    if(window.Toastify){ Toastify({text:message,duration:3000}).showToast(); }
    else { alert(message); }
  }
  $(document).on('submit','.jp-search',function(e){
    e.preventDefault();
    var $form=$(this); var target=$form.data('target')||'#jp-job-results';
    var data=$form.serializeArray(); data.push({name:'action',value:'jportal_search_jobs'}); data.push({name:'nonce',value:JPortalCore.nonce});
    $(target).addClass('jp-loading');
    $.get(JPortalCore.ajaxUrl,data,function(resp){
      if(resp.success){ $(target).html(resp.data.html||'<div class="jp-notice">No jobs found.</div>'); }
      else { notice(resp.data&&resp.data.message?resp.data.message:'Search failed.'); }
    }).always(function(){ $(target).removeClass('jp-loading'); });
  });
  $(document).on('click','.jp-save-job',function(e){
    e.preventDefault(); var job=$(this).data('job');
    $.post(JPortalCore.ajaxUrl,{action:'jportal_save_job',nonce:JPortalCore.nonce,job_id:job},function(resp){ notice(resp.data&&resp.data.message?resp.data.message:'Saved.'); });
  });
  $(document).on('submit','.jp-apply-form',function(e){
    e.preventDefault(); var data=$(this).serializeArray(); data.push({name:'action',value:'jportal_apply_job'}); data.push({name:'nonce',value:JPortalCore.nonce});
    $.post(JPortalCore.ajaxUrl,data,function(resp){ notice(resp.data&&resp.data.message?resp.data.message:'Application submitted.'); if(resp.success){ $('.jp-apply-form')[0].reset(); } });
  });
  $(document).on('submit','.jp-alert-form',function(e){
    e.preventDefault(); var data=$(this).serializeArray(); data.push({name:'action',value:'jportal_create_alert'}); data.push({name:'nonce',value:JPortalCore.nonce});
    $.post(JPortalCore.ajaxUrl,data,function(resp){ notice(resp.data&&resp.data.message?resp.data.message:'Alert saved.'); });
  });
  $(document).on('submit','.jp-message-form',function(e){
    e.preventDefault(); var data=$(this).serializeArray(); data.push({name:'action',value:'jportal_send_message'}); data.push({name:'nonce',value:JPortalCore.nonce});
    $.post(JPortalCore.ajaxUrl,data,function(resp){ notice(resp.data&&resp.data.message?resp.data.message:'Message sent.'); });
  });
})(jQuery);
