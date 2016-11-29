M.enrol_ucsfsis={
  Y : null,
  transaction : [],
  init : function(Y, params){
    this.Y = Y;
    var formid = params.formid;
    var courseid = params.courseid;
    var selectTerm = Y.one('#' + formid + ' #id_selectterm');
    var submitTerm = Y.one('#' + formid + ' #id_submitterm');
    var selectRole = Y.one('#' + formid + ' #id_roleid');
    var selectSubjectCourse = Y.all('#' + formid + ' .fhierselect select');
    var submitbutton = Y.one('#' + formid + ' #id_submitbutton');

    YUI().use('node-event-simulate', function(Y) {
      var submitTerm = Y.one('#' + formid + ' #id_submitterm');

      selectTerm.on('change', function() {
        // Disable all other select elements
        // (OK, need a better way to disable inputs while fetching new page.)
        selectRole.setAttribute('disabled','disabled');
        selectSubjectCourse.each( function( selectNode ) {
          selectNode.setAttribute('disabled', 'disabled');
        } );
        // Disable the submit 'Save' button
        submitbutton.setAttribute('disabled', 'disabled');
        submitTerm.simulate('click');
      });
    });

/*
 * This is taking too long to return...need a clock or progress bar.
 */
/*
    selectTerm.on('change', function(e) {
      // var el_name = e.currentTarget.get('name');
      // var el_value = e.currentTarget.get('value');
      // alert('name: ' + el_name + '<br />value: ' + el_value);

      var termid = e.currentTarget.get('value');

      // Create a YUI instance using io-base module.
      YUI().use("io-base", function(Y) {
        var uri = "/enrol/ucsfsis/ajax.php?id="+courseid+"&action=gettermoptions&termid="+termid+'&sesskey='+M.cfg.sesskey;

        // Define a function to handle the response data.
        function complete(id, o, args) {
          var id = id; // Transaction ID.
          var data = o.responseText; // Response data.
          var args = args[1]; // 'ipsum'.
          alert('ajax returns "' + data + '"');

          // if (_hs_options['selectsubjectcourse'] is defined) {
          //   _hs_options = response.response[1];
          // }
        };

        // Subscribe to event "io:complete", and pass an array
        // as an argument to the event handler "complete", since
        // "complete" is global.   At this point in the transaction
        // lifecycle, success or failure is not yet known.
        Y.on('io:complete', complete, Y, ['lorem', 'ipsum']);

        // Make an HTTP request to 'get.php'.
        // NOTE: This transaction does not use a configuration object.
        var request = Y.io(uri);
      });

    });
*/
    submitTerm.setStyle('display', 'none');
  }
}