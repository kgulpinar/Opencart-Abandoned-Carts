$(document).ready(function() {
    // Load the coupons view
    refreshData();
    
    // Get the abandoned carts view tab
    getAbandonedCarts();
    
    // Initialize the discount selectors for the already active mail templates
    selectorsForDiscount();

    // NProgress initialize (for the mail templates effects)
    NProgress.configure({
        showSpinner: false,
        ease: 'ease',
        speed: 500,
        trickleRate: 0.2,
        trickleSpeed: 200 
    });
    
    // Tabs init
    $('#mainTabs a:first').tab('show');
    $('.mail-list').children().last().children('a').click();
    
    if (window.localStorage && window.localStorage['currentTab']) {
        $('.mainMenuTabs a[href="'+window.localStorage['currentTab']+'"]').tab('show');
    }
    
    if (window.localStorage && window.localStorage['currentSubTab']) {
        $('a[href="'+window.localStorage['currentSubTab']+'"]').tab('show');
    }
    
    $('.mainMenuTabs a[data-toggle="tab"]').click(function() {
        if (window.localStorage) {
            window.localStorage['currentTab'] = $(this).attr('href');
        }
    });
    
    $('a[data-toggle="tab"]:not(.mainMenuTabs a[data-toggle="tab"], .mailtemplate_tabs a[data-toggle="tab"])').click(function() {
        if (window.localStorage) {
            window.localStorage['currentSubTab'] = $(this).attr('href');
        }
    });
    
});

$(window).load(function(){
    $('#sendReminderModalBody').css('height', $(window).height() * 0.72 + 'px');
});

$(window).resize(function() {
    $('#sendReminderModalBody').css('height', $(window).height() * 0.72 + 'px')
});

function getAbandonedCarts() {
    $.ajax({
        url: "index.php?route=" + modulePath + "/getabandonedcarts&" + token_addon + "&page=1&store_id=" + store_id,
        type: 'get',
        dataType: 'html',
        success: function(data) {		
            $("#abandonedCartsWrapper" + store_id).html(data);
        }
    });   
}

// Coupons view
function refreshData(){
    $.ajax({
        url: "index.php?route=" + modulePath + "/givenCoupons&" + token_addon,
        type: 'get',
        dataType: 'html',
        success: function(data) { 
            $('#givenCoupons').html(data);
        }
    });
    
    $.ajax({
        url: "index.php?route=" + modulePath + "/usedCoupons&" + token_addon,
        type: 'get',
        dataType: 'html',
        success: function(data) { 
            $('#usedCoupons').html(data);
        }
    });
}

// Remove expired coupons action
function removeExpiredCoupons() {      
	var r = confirm(alertRemoveExpiredCouponsText);
	if (r==true) {
		$.ajax({
			url: 'index.php?route=' + modulePath + '/removeallexpiredcoupons&' + token_addon,
			type: 'post',
			data: {'remove': r },
			success: function(response) {
				alert(alertRemoveExpiredCouponsTextSuccess);
				location.reload();
			}
		});
 	}
}

// Send reminder option, obviously
function sendReminder(cartID, templateID) {
    $('#sendReminderModalBody').html("");      
    $('#sendReminderModalBody').load('index.php?route=' + modulePath + '/sendReminder&' + token_addon + '&id=' + cartID + '&template_id=' + templateID + '&store_id=' + store_id);
    $('#sendReminderModal').modal();
}

// Remove record from the module
function removeItem(cartID) {      
    var r=confirm(alertConfirmRemoveEntry);
    if (r==true) {
        $.ajax({
            url: 'index.php?route=' + modulePath + '/removeabandonedcart&' + token_addon,
            type: 'post',
            data: {'cart_id': cartID},
            success: function(response) {
				location.reload();
			}
		});
	 }
}

// Send email modal
$(document).ready(function(e) {
    $('#sendReminderModal').delegate('#sendMail', 'click', function(e) {
        e.preventDefault();
        var language = $('input[name="AB_language_id"]').val();
        var template = $('input[name="AB_template_id"]').val();
            
        try {
            var content = $('#SendReminderCustomForm textarea[name="abandonedcarts[MailTemplate][' + template + '][Message][' + language + ']"]').html($('#SendReminderCustomForm  #message_' + template + '_' + language).code());
        } catch (err) {
            if (err.message.indexOf('is not a function') > -1) {
                var content = $('#SendReminderCustomForm textarea[name="abandonedcarts[MailTemplate][' + template + '][Message][' + language + ']"]').html($('#SendReminderCustomForm  #message_' + template + '_' + language).summernote('code'));
            }
        }
    
        var email_validate = /^(([^<>()\[\]\.,;:\s@\"]+(\.[^<>()\[\]\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
        var email = $('#customer_email').val().trim();
        
        if (!email.match(email_validate)) {
            alert(alert_invalid_email)
        } else {
            var btn = $(this);
            btn.button('loading');
            $.ajax({
                url: 'index.php?route=' + modulePath + '/sendcustomemail&' + token_addon,
                type: 'post',
                data: $('#SendReminderCustomForm').serialize(),
                success: function(response) {
                     $('#sendReminderModal').modal('hide');
                     $('#messageResult').show();
                     $('#messageResult').delay(3000).fadeOut(600, function(){
                          $(this).hide(); 
                     }).slideUp(600);
                     location.reload();
                },
                error: function(xhr) {
                  alert(xhr.responseText);
                }
            }).always(function () {
                btn.button('reset');
            });
        }
    });
});

// Selectors for discount
function selectorsForDiscount() {
	$('.discountTypeSelect').each(function() {
		if ($(this).val() == 'P'){
			$(this).parents('.templates').find('#percentageAddon').show();
		} else {
			$(this).parents('.templates').find('#currencyAddon').show();
		}
		$(this).parents('.templates').find('.discountMailSelect').each(function() {
			if ($(this).val() == 'yes'){
				$(this).parents('.templates').find('.discountMailSettings').show();
			} else {
				$(this).parents('.templates').find('.discountMailSettings').hide();
			}
		});
		if ($(this).val() == 'N'){
			$(this).parents('.templates').find('.discountSettings').hide();
			$(this).parents('.templates').find('.discountMailSettings').hide();
		} else {
			$(this).parents('.templates').find('.discountSettings').show();
		}
	});

	$('.discountMailSelect').on('change', function(e){ 
		if($(this).val() == 'yes') {
			$(this).parents('.templates').find('.discountMailSettings').show(300);
		} else {
			$(this).parents('.templates').find('.discountMailSettings').hide(300);
		}	
	});
	
	$('.discountTypeSelect').on('change', function(e){ 
		if($(this).val() == 'P') {
			$(this).parents('.templates').find('#percentageAddon').show();
			$(this).parents('.templates').find('#currencyAddon').hide();
		} else {
			$(this).parents('.templates').find('#currencyAddon').show();
			$(this).parents('.templates').find('#percentageAddon').hide();
		}
		//
		$(this).parents('.templates').find('.discountMailSelect').each(function() {
			if ($(this).val() == 'yes'){
				$(this).parents('.templates').find('.discountMailSettings').show();
			} else {
				$(this).parents('.templates').find('.discountMailSettings').hide();
			}
		});
		//	
		if($(this).val() == 'N') {
			$(this).parents('.templates').find('.discountSettings').hide(300);
			$(this).parents('.templates').find('.discountMailSettings').hide();
		} else {
			$(this).parents('.templates').find('.discountSettings').show(300);
		}
	});
}

// Add Template
function addNewMailTemplate() {
	count = $('.mail-list li:last-child > a').data('mailtemplate-id') + 1 || 1;
	var ajax_data = {};
	//ajax_data.token = token;
	ajax_data.store_id = store_id;
	ajax_data.mailtemplate_id = count;

	$.ajax({
		url: 'index.php?route=' + modulePath + '/get_mailtemplate_settings&' + token_addon,
		data: ajax_data,
		dataType: 'html',
		beforeSend: function() {
			NProgress.start();
		},
		success: function(settings_html) {
			$('.mail-settings').append(settings_html);
			
			if (count == 1) { $('a[href="#mailtemplate_'+ count +'"]').tab('show'); }
			tpl 	= '<li>';
			tpl 	+= '<a href="#mailtemplate_'+ count +'" data-toggle="tab" data-mailtemplate-id="'+ count +'">';
			tpl 	+= '<i class="fa fa-pencil-square-o"></i> ' + text_template + ' '+ count;
			tpl 	+= '<i class="fa fa-minus-circle removeMailTemplate"></i>';
			tpl 	+= '<input type="hidden" name="' + moduleName + '[MailTemplate]['+ count +'][id]" value="'+ count +'"/>';
			tpl 	+= '</a>';
			tpl	+= '</li>';
			
			$('.mail-list').append(tpl);
			$('button[data-event=\'showImageDialog\']').attr('data-toggle', 'image').removeAttr('data-event');
			
			NProgress.done();
			$('.mail-list').children().last().children('a').trigger('click');
			window.localStorage['currentSubTab'] = $('.mail-list').children().last().children('a').attr('href');
		}
	});
}

// Remove Template
function removeMailTemplate() {
	tab_link = $(event.target).parent();
	tab_pane_id = tab_link.attr('href');
	
	var confirmRemove = confirm(confirm_template_remove + ' ' + tab_link.text().trim() + '?');
	
	if (confirmRemove == true) {
		tab_link.parent().remove();
		$(tab_pane_id).remove();
		
		if ($('.mail-list').children().length > 1) {
			$('.mail-list > li:nth-child(2) a').tab('show');
			window.localStorage['currentSubTab'] = $('.mail-list > li:nth-child(2) a').attr('href');
		}
	}
}

// Events for the Add and Remove buttons
$(document).ready(function() {
	// Add New Label
	$('.addNewMailTemplate').click(function(e) { addNewMailTemplate(); });
	// Remove Label
	$('.mail-list').delegate('.removeMailTemplate', 'click', function(e) { removeMailTemplate(); });
});

// Date & Time picker
$(document).ready(function() {	
	$('#FixedDate').datetimepicker({ pickTime: false });
	$('.timepicker').datetimepicker({ pickDate: false });
	
    $('#CronSelector').cron({
        initial: cron_initial_settings,
        onChange: function() {
            $('#abCron').val($(this).cron("value"));		 
        }
	});
    
    if($('select[name="' + moduleName + '[ScheduleType]"]').val() == 'P') {
        $('#FixedDateOptions').hide();
        $('#PeriodicOptions').show(200);
    } else {
        $('#PeriodicOptions').hide();
        $('#fixedDateOptions').show(200);	
    }
    $('select[name="' + moduleName + '[ScheduleType]"]').on('change', function(e){ 
        if($(this).val() == 'P') {
            $('#FixedDateOptions').hide();
            $('#PeriodicOptions').show(200);	
        } else {
            $('#PeriodicOptions').hide();
            $('#FixedDateOptions').show(200);	
        }	
    });
});


// CRON date + CRON modal
$(document).ready(function() {	
    $('.cronBtn').on('click', function(e){
        var modal = $('#cronModal'), modalBody = $('#cronModal .modal-body');
        modal
            .on('show.bs.modal', function () {
                modalBody.load('index.php?route=' + modulePath + '/testcron&' + token_addon)
            })
            .modal();
        e.preventDefault();
    });

    $('.btn.addDate').on('click', function(e){
        e.preventDefault();
        if($('#FixedDate').val() && $('#FixedDateTime').val() ){
            $('.scrollbox.dateList').append('<div id="date' + $('#FixedDate').val().replace(/\./g,'') + '">' + $('#FixedDate').val() + '/' + $('#FixedDateTime').val() +'<i class="fa fa-minus-circle removeIcon"></i><input type="hidden" name="' + moduleName + '[FixedDates][]" value="' + $('#FixedDate').val() + '/' + $('#FixedDateTime').val() + '" /></div>');
            $('#FixedDate').val('');
            $('#FixedDateTime').val('');
        } else {
            alert(alert_date_time);
        }
        refreshRemoveButtonForTheCronJobs();
    });

    refreshRemoveButtonForTheCronJobs();
});

function refreshRemoveButtonForTheCronJobs() {
	$('.scrollbox.dateList div .removeIcon').click(function() {
		$(this).parent().remove();
	});
}

// Hide & Show Scheduled table
$(function() {
    var $typeSelector = $('#ScheduleToggle');
    var $toggleArea = $('#mainSettings');
	 
    $typeSelector.change(function(){
        if ($typeSelector.val() === 'yes') {
            $toggleArea.show(500) 
        }
        else {
            $toggleArea.hide(500);
        }
    });
});

// Tooltip initialization
$(function () {
    $('.btn-send').tooltip({
        animation: true,
        placement: "bottom"
    });
});

// Remove all records
function removeAll() {      
    var r=confirm(confirm_remove_all_records);
    if (r==true) {
        $.ajax({
            url: 'index.php?route=' + modulePath + '/removeallrecords&store=' + store_id + '&' + token_addon,
            type: 'post',
            data: {'remove': r, 'store' : store_id },
            success: function(response) {
                location.reload();
        }
    });
 }
}

// Remove all empty records
function removeAllEmpty() {      
    var r=confirm(confirm_remove_all_empty_records);
    if (r==true) {
        $.ajax({
            url: 'index.php?route=' + modulePath + '/removeallemptyrecords&store=' + store_id + '&' + token_addon,
            type: 'post',
            data: {'remove': r, 'store' : store_id },
            success: function(response) {
                location.reload();
            }
        });
    }
}

// Wrapper for the abandoned records view
$(document).ready(function() {
    $('#abandonedCartsWrapper' + store_id).delegate('.pagination a', 'click', (function(e){
        e.preventDefault();
        $.ajax({
            url: this.href,
            type: 'get',
            dataType: 'html',
            success: function(data) {				
                $("#abandonedCartsWrapper" + store_id).html(data);
            }
        });
    }));	
    
    $('#abandonedCartsWrapper' + store_id).delegate('.filter a', 'click', (function(e){
        e.preventDefault();
        $.ajax({
            url: this.href,
            type: 'get',
            dataType: 'html',
            success: function(data) {				
                $("#abandonedCartsWrapper" + store_id).html(data);
            }
        });
    }));	 
});
