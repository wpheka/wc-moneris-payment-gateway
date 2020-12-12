jQuery(document).ready(function($) {
	$('.img_tip').each(function() {
		$(this).qtip({
			content: $(this).attr('data-desc'),
			position: {
				my: 'center left',
				at: 'center right',
				viewport: $(window)
			},
			show: {
				event: 'mouseover',
				solo: true,
			},
			hide: {
				inactive: 6000,
				fixed: true
			},
			style: {
				classes: 'qtip-dark qtip-shadow qtip-rounded qtip-dc-css'
			}
		});
	});
	
	$('.dc_datepicker').each(function() {
	  $(this).datepicker({
      dateFormat : $(this).data('date_format'),
      changeMonth: true,
      changeYear: true
    });
  });
	
  $('.multi_input_holder').each(function() {
	  var multi_input_holder = $(this);
	  addMultiInputProperty(multi_input_holder);
	});
	
	function addMultiInputProperty(multi_input_holder) {
	  if(multi_input_holder.children('.multi_input_block').length == 1) multi_input_holder.children('.multi_input_block').children('.remove_multi_input_block').css('display', 'none'); 
    multi_input_holder.children('.multi_input_block').each(function() {
      if($(this)[0] != multi_input_holder.children('.multi_input_block:last')[0]) {
        $(this).children('.add_multi_input_block').remove();
      }
    });
    
    multi_input_holder.children('.multi_input_block').children('.add_multi_input_block').click(function() {
      var holder_id = multi_input_holder.attr('id');
      var holder_name = multi_input_holder.data('name');
      var multi_input_blockCount = multi_input_holder.data('length');
      multi_input_blockCount++;
      var multi_input_blockEle = multi_input_holder.children('.multi_input_block:first').clone(false);
      
      multi_input_blockEle.find('textarea,input:not(input[type=button],input[type=submit],input[type=checkbox],input[type=radio])').val('');
      multi_input_blockEle.children('.multi_input_block_element:not(.multi_input_holder)').each(function() {
        var ele = $(this);
        var ele_name = ele.data('name');
        ele.attr('name', holder_name+'['+multi_input_blockCount+']['+ele_name+']');
        ele.attr('id', holder_id + '_' + ele_name + '_' + multi_input_blockCount);
      });
      
      // Nested multi-input block property
      multi_input_blockEle.children('.multi_input_holder').each(function() {
        setNestedMultiInputIndex($(this), holder_id, holder_name, multi_input_blockCount);
      });
       
      
      multi_input_blockEle.children('.remove_multi_input_block').click(function() {
      	var remove_ele_parent = $(this).parent().parent();
				var addEle = remove_ele_parent.children('.multi_input_block').children('.add_multi_input_block').clone(true);
				$(this).parent().remove();
				remove_ele_parent.children('.multi_input_block').children('.add_multi_input_block').remove();
				remove_ele_parent.children('.multi_input_block:last').append(addEle);
				if(remove_ele_parent.children('.multi_input_block').length == 1) remove_ele_parent.children('.multi_input_block').children('.remove_multi_input_block').css('display', 'none');
			});
      
      multi_input_blockEle.children('.add_multi_input_block').remove();
      multi_input_holder.append(multi_input_blockEle);
      multi_input_holder.children('.multi_input_block:last').append($(this));
      if(multi_input_holder.children('.multi_input_block').length > 1) multi_input_holder.children('.multi_input_block').children('.remove_multi_input_block').css('display', 'block');
      multi_input_holder.data('length', multi_input_blockCount);
    });
    
    if(!multi_input_holder.hasClass('multi_input_block_element')) {
			multi_input_holder.children('.multi_input_block').css('padding-bottom', '40px');
		}
		if(multi_input_holder.children('.multi_input_block').children('.multi_input_holder').length > 0) {
			multi_input_holder.children('.multi_input_block').css('padding-bottom', '40px');
		}
    
    multi_input_holder.children('.multi_input_block').children('.remove_multi_input_block').click(function() {
    	var remove_ele_parent = $(this).parent().parent();
      var addEle = remove_ele_parent.children('.multi_input_block').children('.add_multi_input_block').clone(true);
      $(this).parent().remove();
      remove_ele_parent.children('.multi_input_block').children('.add_multi_input_block').remove();
      remove_ele_parent.children('.multi_input_block:last').append(addEle);
      if(remove_ele_parent.children('.multi_input_block').length == 1) remove_ele_parent.children('.multi_input_block').children('.remove_multi_input_block').css('display', 'none');
    });
  }
  
  function setNestedMultiInputIndex(nested_multi_input, holder_id, holder_name, multi_input_blockCount) {
		nested_multi_input.children('.multi_input_block:not(:last)').remove();
		var multi_input_id = nested_multi_input.attr('id');
		console.log(multi_input_id);
		multi_input_id = multi_input_id.replace(holder_id + '_', '');
		console.log(holder_id +":" + holder_name +":"+ multi_input_id);
		var multi_input_id_splited = multi_input_id.split('_');
		var multi_input_name = '';
		for(var i = 0; i < (multi_input_id_splited.length -1); i++) {
		 if(multi_input_name != '') multi_input_name += '_';
		 multi_input_name += multi_input_id_splited[i];
		}
		nested_multi_input.attr('data-name', holder_name+'['+multi_input_blockCount+']['+multi_input_name+']');
		nested_multi_input.attr('id', holder_id+'_'+multi_input_name+'_'+multi_input_blockCount);
		nested_multi_input.children('.multi_input_block').children('.multi_input_block_element:not(.multi_input_holder)').each(function() {
		  var ele = $(this);
		  var ele_name = ele.data('name');
		  ele.attr('name', holder_name+'['+multi_input_blockCount+']['+multi_input_name+'][0]['+ele_name+']');
		  ele.attr('id', holder_id+'_'+multi_input_name+'_'+multi_input_blockCount + '_' + ele_name + '_0');
		});
		
		addMultiInputProperty(nested_multi_input);
		
		if(nested_multi_input.children('.multi_input_block').children('.multi_input_holder').length > 0) nested_multi_input.children('.multi_input_block').css('padding-bottom', '40px');
		
		nested_multi_input.children('.multi_input_block').children('.multi_input_holder').each(function() {
			setNestedMultiInputIndex($(this), holder_id+'_'+multi_input_name+'_0', holder_name+'['+multi_input_blockCount+']['+multi_input_name+']', 0);
		});
	}
});

/*
if(ele.parent().hasClass('dc-wp-fields-uploader')) {
	var uploadEle = ele.parent();
	uploadEle.find('img').attr('src', '').attr('id', holder_id + '_' + ele_name + '_' + multi_input_blockCount + '_display').addClass('placeHolder');
	uploadEle.find('.upload_button').attr('id', holder_id + '_' + ele_name + '_' + multi_input_blockCount + '_button').show();
	uploadEle.find('.remove_button').attr('id', holder_id + '_' + ele_name + '_' + multi_input_blockCount + '_remove_button').hide();
}

if(ele.hasClass('dc_datepicker')) {
	ele.removeClass('hasDatepicker').datepicker({
		dateFormat : ele.data('date_format'),
		changeMonth: true,
		changeYear: true
	});
}
*/
