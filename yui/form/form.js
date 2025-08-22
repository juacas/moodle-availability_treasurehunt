YUI.add('moodle-availability_treasurehunt-form', function(Y, NAME) {

    M.availability_treasurehunt = M.availability_treasurehunt || {};

    M.availability_treasurehunt.form = Y.Object(M.core_availability.plugin);

    M.availability_treasurehunt.form.initInner = function(cmid, treasurehunts) {
        this.treasurehunts = treasurehunts;
        this.cmid = cmid;
    };

    M.availability_treasurehunt.form.getNode = function(json) {
        var html = '<label><span class="p-r-1">' + M.util.get_string('select_treasurehunt', 'availability_treasurehunt') + '</span>';
        html += '<select name="treasurehuntid" class="custom-select">';
        html += '<option value="">' + M.util.get_string('choosedots', 'moodle') + '</option>';

        for (var k in this.treasurehunts) {
            html += '<option value="' + this.treasurehunts[k].id + '">' + this.treasurehunts[k].display + '</option>';
        }

        html += '</select></label>';
        html += '<br><div class="condition-fields" style="display: none;">';
        html += '<label><span class="p-r-1">' + M.util.get_string('condition_type', 'availability_treasurehunt') + '</span>';
        html += '<select name="conditiontype" class="custom-select">';
        html += '<option value="stages">' + M.util.get_string('stages_completed', 'availability_treasurehunt') + '</option>';
        html += '<option value="time">' + M.util.get_string('time_played', 'availability_treasurehunt') + '</option>';
        html += '<option value="completion">' + M.util.get_string('full_completion', 'availability_treasurehunt') + '</option>';
        html += '<option value="current_stage">' + M.util.get_string('current_stage', 'availability_treasurehunt') + '</option>';
        html += '</select></label>';
        html += '<br><label><span class="p-r-1">' + M.util.get_string('minimum_stages', 'availability_treasurehunt') + '</span>';
        html += '<input type="number" name="requiredvalue" min="0" class="form-control" style="width: 100px; display: inline-block;"></label>';
        html += '<label><span class="p-r-1">' + M.util.get_string('select_stage', 'availability_treasurehunt') + '</span>';
        html += '<select name="stageid" class="custom-select" style="display: inline-block;">';
        html += '</select></label>';
        html += '</div>';

        var node = Y.Node.create('<span class="form-group">' + html + '</span>');

        // Get form elements
        var treasurehuntSelect = node.one('select[name=treasurehuntid]');
        var conditionFieldsDiv = node.one('.condition-fields');
        var conditionSelect = node.one('select[name=conditiontype]');
        var valueInput = node.one('input[name=requiredvalue]');
        var stageSelect = node.one('select[name=stageid]');
        var stageLabel = stageSelect.get('parentNode').one('span');

        // Set initial values
        if (json.treasurehuntid) {
            treasurehuntSelect.set('value', json.treasurehuntid);
        }
        if (json.conditiontype) {
            conditionSelect.set('value', json.conditiontype);
        }
        if (json.requiredvalue !== undefined) {
            valueInput.set('value', json.requiredvalue);
        }
        if (json.stageid) {
            stageSelect.set('value', json.stageid);
        }

        // Function to load stages using core/ajax module
        var loadStages = function() {
            var treasurehuntId = treasurehuntSelect.get('value');
            if (!treasurehuntId) {
                // Clear stages when no treasure hunt is selected
                stageSelect.get('childNodes').remove();
                return;
            }

            // Use require to load the core/ajax module
            require(['core/ajax', 'core/notification'], function(Ajax, Notification) {
                var request = {
                    methodname: 'availability_treasurehunt_get_stages',
                    args: {
                        treasurehuntid: parseInt(treasurehuntId, 10)
                    }
                };

                Ajax.call([request])[0]
                    .then(function(stages) {
                        // Clear existing options
                        stageSelect.get('childNodes').remove();

                        // Add new options
                        Y.Array.each(stages, function(stage) {
                            stageSelect.append('<option value="' + stage.id + '">' + stage.name + '</option>');
                        });

                        // Set the stage value (either from json or empty )
                        var stageValue = json.stageid || '';
                        stageSelect.set('value', stageValue);

                        // Update form if we had a previous value
                        if (json.stageid) {
                            M.core_availability.form.update();
                        }
                    })
                    .catch(function(error) {
                        Notification.exception(error);
                        console.error('Error loading stages:', error);

                        // On error, clear the stage select options.
                        stageSelect.get('childNodes').remove();
                    });
            });
        };

        // Show/hide condition fields based on treasure hunt selection
        var updateConditionFieldsVisibility = function() {
            var treasurehuntId = treasurehuntSelect.get('value');
            if (treasurehuntId) {
                conditionFieldsDiv.setStyle('display', 'block');
            } else {
                conditionFieldsDiv.setStyle('display', 'none');
                // Reset condition type to default when hiding
                conditionSelect.set('value', 'stages');
            }
        };

        // Show/hide fields based on condition type
        var updateFields = function() {
            var type = conditionSelect.get('value');
            var valueLabel = valueInput.get('parentNode').one('span');

            if (type === 'stages') {
                valueInput.setStyle('display', 'inline-block');
                valueLabel.setContent(M.util.get_string('minimum_stages', 'availability_treasurehunt'));
                stageSelect.setStyle('display', 'none');
                stageLabel.setContent('');
            } else if (type === 'time') {
                valueInput.setStyle('display', 'inline-block');
                valueLabel.setContent(M.util.get_string('minimum_time', 'availability_treasurehunt'));
                stageSelect.setStyle('display', 'none');
                stageLabel.setContent('');
            } else if (type === 'current_stage') {
                valueInput.setStyle('display', 'none');
                valueLabel.setContent('');
                stageLabel.setContent(M.util.get_string('select_stage', 'availability_treasurehunt'));
                stageSelect.setStyle('display', 'inline-block');
                loadStages();
            } else {
                valueInput.setStyle('display', 'none');
                valueLabel.setContent('');
                stageSelect.setStyle('display', 'none');
                stageLabel.setContent('');
            }
        };

        // Event handlers
        treasurehuntSelect.on('change', function() {
            updateConditionFieldsVisibility();
            // Reset stage selection when treasure hunt changes
            if (conditionSelect.get('value') === 'current_stage') {
                loadStages();
            }
            M.core_availability.form.update();
        });

        conditionSelect.on('change', function() {
            updateFields();
            M.core_availability.form.update();
        });

        valueInput.on('change', function() {
            M.core_availability.form.update();
        });

        stageSelect.on('change', function() {
            M.core_availability.form.update();
        });

        // Initialize fields
        updateConditionFieldsVisibility();
        updateFields();

        return node;
    };

    M.availability_treasurehunt.form.fillValue = function(value, node) {
        value.treasurehuntid = parseInt(node.one('select[name=treasurehuntid]').get('value'), 10);
        value.conditiontype = node.one('select[name=conditiontype]').get('value');
        value.requiredvalue = parseInt(node.one('input[name=requiredvalue]').get('value'), 10) || 0;
        value.stageid = parseInt(node.one('select[name=stageid]').get('value'), 10) || 0;
    };

    M.availability_treasurehunt.form.fillErrors = function(errors, node) {
        var treasurehuntid = parseInt(node.one('select[name=treasurehuntid]').get('value'), 10);
        var conditiontype = node.one('select[name=conditiontype]').get('value');
        var requiredvalue = parseInt(node.one('input[name=requiredvalue]').get('value'), 10);
        var stageid = parseInt(node.one('select[name=stageid]').get('value'), 10);

        if (!treasurehuntid) {
            errors.push('availability_treasurehunt:error_selecttreasurehunt');
        }

        if ((conditiontype === 'stages' || conditiontype === 'time') && (!requiredvalue || requiredvalue < 1)) {
            errors.push('availability_treasurehunt:error_setcondition');
        }

        if (conditiontype === 'current_stage' && !stageid) {
            errors.push('availability_treasurehunt:error_selectstage');
        }
    };

}, '@VERSION@', {
    requires: ['base', 'node', 'event', 'moodle-core_availability-form']
});