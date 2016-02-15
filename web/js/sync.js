/**
 * Created by Romain on 10/04/2015.
 */
$().ready(function() {
   startSync();
});

var syncRunning = null;

function startSync(){
    if(!syncRunning){
        syncRunning = true;

        $.post('/PgExplorer/web/app_dev.php/sync/compare-structure/step/schemas',
            {},
            function(data) {

                if(data.ok){
                    var html = '<div class="bg-success" style="padding:5px;">Schemas : '+data.message+'</div>';
                    $('#syncStatus').append(html);
                    compareTables();
                }else{
                    var html = '<div class="bg-danger" style="padding:5px;">Schemas : '+data.message+'</div>';
                    $('#syncStatus').append(html);
                }
            },
            'json');
    }

}

function compareTables(){
    $.post('/PgExplorer/web/app_dev.php/sync/compare-structure/step/tables',
        {},
        function(data) {

            if(data.ok){
                var html = '<div class="bg-success" style="padding:5px;">Tables : '+data.message+'</div>';
                $('#syncStatus').append(html);
                compareFunctions();
            }else if(data.message){
                var html = '<div class="bg-danger" style="padding:5px;">Tables : '+data.message+'</div>';
                $('#syncStatus').append(html);
            }else{
                var html = '<div class="bg-danger" style="padding:5px;">Tables : '+data+'</div>';
                $('#syncStatus').append(html);
            }
        },
        'json');
}

function compareFunctions(){
    $.post('/PgExplorer/web/app_dev.php/sync/compare-structure/step/functions',
        {},
        function(data) {

            if(data.ok){
                var html = '<div class="bg-success" style="padding:5px;">Functions : '+data.message+'</div>';
                $('#syncStatus').append(html);

                syncData();
            }else{
                var html = '<div class="bg-danger" style="padding:5px;">Functions : '+data.message+'</div>';
                $('#syncStatus').append(html);
            }
        },
        'json');
}

var keepSyncData = true;
function syncData(){

    $.post('/PgExplorer/web/app_dev.php/sync/get-weights',
        {},
        function(data) {

            if(data.ok){
               for(index in data.weights){
                   var info = data.weights[index];
                   if(keepSyncData){
                       syncWeight(info.weight, info.limit, info.nbTables);
                   }

               }
                var html = '<div class="bg-success" style="padding:5px;">Sync finished</div>';
                $('#syncStatus').append(html);

            }else{
                var html = '<div class="bg-danger" style="padding:5px;">Technical error : '+data.message+'</div>';
                $('#syncStatus').append(html);
            }
        },
        'json');
}

function syncWeight(weight, limit,max){
    if(limit == 0){
        $.ajax({url:'/PgExplorer/web/app_dev.php/sync/sync-data/weight/'+weight+'/limit/'+limit,
            method:'POST',
            async:false,
            success: function(data) {

                if(data.ok){
                    var html = '<div class="bg-success" style="padding:5px;">' + data.nbTablesSync +' / ' + data.nbTables+' Tables with '+weight+' dependance : '+data.message+'</div>';
                    $('#syncStatus').append(html);
                    keepSyncData = true;
                }else if(data.message){
                    var html = '<div class="bg-danger" style="padding:5px;">'+weight+' : '+data.message+'</div>';
                    $('#syncStatus').append(html);
                    keepSyncData = false;
                }else{
                    var html = '<div class="bg-danger" style="padding:5px;">'+weight+' : '+data+'</div>';
                    $('#syncStatus').append(html);
                    keepSyncData = false;
                }
            },
            dataType :'json'});
    }else{
        var index = 1;
        while(index <= max){
            $.ajax({url:'/PgExplorer/web/app_dev.php/sync/sync-data/weight/'+weight+'/limit/'+limit,
                method:'POST',
                async:false,
                success: function(data) {

                    if(data.ok){
                        var html = '<div class="bg-success" style="padding:5px;">' + data.nbTablesSync +' / ' + data.nbTables+' Tables with '+weight+' dependance : '+data.message+'</div>';
                        $('#syncStatus').append(html);

                    }else if(data.message){
                        var html = '<div class="bg-danger" style="padding:5px;">'+weight+' : '+data.message+'</div>';
                        $('#syncStatus').append(html);
                    }else{
                        var html = '<div class="bg-danger" style="padding:5px;">'+weight+' : '+data+'</div>';
                        $('#syncStatus').append(html);
                    }
                },
                dataType :'json'});

            index = index + limit;
        }
    }

}