$().ready(function() {
    if($('#leftMenu').find('li').length){
        $('#leftMenu').menu();
    }

    //$('.jButton').button();

    //SQL generator initialization
    initSQLGenerator();
});

$(document).ajaxStart(function() {
    $(".ajaxSpinner").html('<div class="ajaxSpinnerImage" style="width: 100px;background-image:url(\'/css/cupertino/images/animated-overlay.gif\');margin:auto auto;">&nbsp;</div>');
    //$( ".ajaxSpinner" ).show();

});
$(document).ajaxStop(function() {
    $(".ajaxSpinnerImage").remove();
});

function showTable(schema, oid) {
    $('#content').addClass('ajaxSpinner');
    $.post(Routing.generate("browser.getTableInfo"), {schema: schema, oid: oid}, function(data) {
        $('#content').html(data)
    });
}

function showFunction(oid) {
    $.post(Routing.generate("browser.getFunctionInfo"), {oid: oid}, function(data) {
        $('#content').html(data);
    });
}

function showView(schema, oid) {
    $.post('', {oid: oid}, function(data) {
    });
}

function showSequence(schema, oid) {
    $.post('', {oid: oid}, function(data) {
    });
}

function showType(schema, oid) {
    $.post('', {oid: oid}, function(data) {
    });
}

function indentRequest() {
    var request = $('#request').val().replace(/\s*\n+\s*/g, ' ');

    //console.log(request);

    //Mise en forme
    var keywords = /(\s*WITH (RECURSIVE)? \w+ as \(\s*|\s*SELECT(\s+ALL|\s+DISTINCT)?\s+|\s+FROM\s+|\s+(INNER|LEFT|RIGHT|CROSS)?\s+JOIN\s+|\s+WHERE\s+|\s+AND\s+(NOT\s+)?|\s+OR\s+(NOT\s+)?|\s+GROUP\s+BY\s+|\s+HAVING\s+|\s+WINDOW\s+\w+\s+AS\s+\(\s*|\s+UNION\s+(ALL|DISTINCT)?\s*|\s+order\s+by\s+([\d\w]*\s?(asc|desc|using)?,*\s*)+|\s+LIMIT\s+(\d|ALL)+\s*|\s+OFFSET\s+\d\s+(ROW|ROWS)?\s*)/gi;
    console.log(request.match(keywords));
    var result = request.replace(keywords, function(v) {
        //console.log(v);
        var newLine = v;
        if (/(\s+(INNER|LEFT|RIGHT|CROSS)?\s+JOIN\s+|\s+AND\s+(NOT\s+)?|\s+OR\s+(NOT\s+)?)/i.test(v)) {
            newLine = "\n    " + v.replace(/^\s+/gi, '');
        } else {
            newLine = "\n" + v.replace(/^\s+/gi, '');
        }

        //console.log(newLine);
        return newLine;
    }).replace(/AS([\s\w\(])*,/gi, function(v) {
        return v.replace(/^\s+/gi, '') + "\n";
    }).replace(/^\s*/, '');
    //console.log(result);


    $('#request').val(result);
}

function doRequest(option) {
    if (option == 'explain' || option == 'explainAnalyse') {
        $.post(Routing.generate("sqlQuery.doExplain"), $('#sqlForm').serialize()+((option == 'explainAnalyse')?'&analyse=1':''), function(data) {
            $('#result').html(data)
        });
    } else {
        $.post(Routing.generate("sqlQuery.doRequest"), $('#sqlForm').serialize(), function(data) {
            $('#result').html(data)
        });
    }
}

function generateSQL() {
    $.post(Routing.generate("sqlQuery.generateSql"),
            $('#generateSQLForm').serialize(),
            function(data) {
                if (data.sql != '') {
                    $('#request').val(data.sql);
                    indentRequest();
                }
                if (data.message != '') {
                    $('#generatorMrg').html(data.message);
                    $('#generatorMrg').show();
                }
            },
            'json');
}

function addColumn() {
    $('#column').autocomplete("search", $('#column').val());
}

function addTable() {
    $('#table').autocomplete("search", $('#table').val());
}

function addJoin() {

}

function addGroupBy() {
    $('#groupBy').autocomplete("search", $('#groupBy').val());
}

function addOrderBy() {
    $('#orderBy').autocomplete("search", $('#orderBy').val());
}

function initSQLGenerator() {
    $('#table').autocomplete({
        minLength: 2,
        delay: 500,
        source: Routing.generate("sqlQuery.autocomplete")+"?population=table",
        select: function(event, ui) {

            $.post(Routing.generate("sqlQuery.addToSqlGenerator"),
                    {elmt: 'table', id: ui.item.id},
            function(data) {
                if (data.ok) {
                    var libs = data.libelle.split(' as ');
                    var id = libs[0].replace('\.', '_');
                    var infos = libs[0].split('.');
                    var html = '<dd id="' + id + '" schema="' + infos[0] + '" table="' + infos[1] + '">' + data.libelle + '<dd>';
                    $('#tables').append(html);
                    $('#joinTableInput').clone(true, true).appendTo('#' + id).show();
                    $('#columns').show();
                    $('#' + id + ' .joinTable').autocomplete({
                        minLength: 2,
                        delay: 500,
                        source: Routing.generate("sqlQuery.autocomplete")+"?population=generatorJoinTable&table=" + $('#' + id).attr('table') + "&schema=" + $('#' + id).attr('schema'),
                        select: function(event, ui) {

                            $.post(Routing.generate("sqlQuery.addToSqlGenerator"),
                                    {elmt: 'joinTable', id: ui.item.id, table: ui.item.value},
                            function(data) {
                                if (data.ok) {
                                    var html = '<li>' + data.libelle + '</li>';
                                    $('#' + id + ' ul').append(html);
                                } else {
                                    alert('Technical error:\n' + data.message);
                                }
                            },
                                    'json');
                        }
                    });
                } else {
                    alert('Technical error:\n' + data.message);
                }
            },
                    'json');
        }
    });



    $('#column').autocomplete({
        minLength: 2,
        delay: 500,
        source: Routing.generate("sqlQuery.autocomplete")+"?population=generatorCol",
        select: function(event, ui) {

            $.post(Routing.generate("addToSqlGenerator"),
                    {elmt: 'column', id: ui.item.id},
            function(data) {
                if (data.ok) {
                    var html = '<dd>' + data.libelle + ', </dd>';
                    $('#columns').append(html);
                    $('#groupBys, #orderBys').show();
                } else {
                    alert('Technical error:\n' + data.message);
                }
            },
                    'json');
        }
    });


    $('#groupBy').autocomplete({
        minLength: 1,
        delay: 500,
        source: Routing.generate("sqlQuery.autocomplete")+"?population=generatorGroupBy",
        select: function(event, ui) {

            $.post(Routing.generate("addToSqlGenerator"),
                    {elmt: 'groupBy', id: ui.item.id},
            function(data) {
                if (data.ok) {
                    var html = '<dd>' + data.libelle + ', </dd>';
                    $('#groupBys').append(html);
                } else {
                    alert('Technical error:\n' + data.message);
                }
            },
                    'json');
        }
    });

    $('#orderBy').autocomplete({
        minLength: 1,
        delay: 500,
        source: Routing.generate("sqlQuery.autocomplete")+"?population=generatorOrderBy",
        select: function(event, ui) {

            $.post(Routing.generate("addToSqlGenerator"),
                    {elmt: 'orderBy', id: ui.item.id},
            function(data) {
                if (data.ok) {
                    var html = '<dd>' + data.libelle + ', </dd>';
                    $('#orderBys').append(html);
                } else {
                    alert('Technical error:\n' + data.message);
                }
            },
                    'json');
        }
    });
}