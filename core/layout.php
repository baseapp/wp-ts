<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wordpress Troubleshooter</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.11/css/jquery.dataTables.css">
    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap-theme.min.css" integrity="sha384-fLW2N01lMqjakBkx3l/M9EahuwpSfeNvV63J5ezn3uZzapT0u7EYsXMjQV+0En5r" crossorigin="anonymous">

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body>
<div class="container" style="margin-top:30px">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-default">
            <div class="panel-heading"><span class="panel-title"><strong id="title">Welcome to WordPress TroubleShooter</strong></span>
                    <span class="pull-right" id="search-box">
                    <input type="text" id="quick-search">
                    </span>
                <br>
            </div>
            <div>
                <ol class="breadcrumb" style="font-size:12px;">
                </ol>
                <ul class="list-group text-info" style="" id="quick-links">
                </ul>
            </div>
            <img src="wp-admin/images/loading.gif" style="margin-left: 50%; display: none;" id="loading">
            <div class="panel-body">
                <div id="simpledata">
                </div>
                <div id="formBody">
                    <form><input type="hidden" value="/home" name="link">
                        <input type="submit" value="Let\'s Start" class="btn btn-primary">
                    </form></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="myModalLabel"></h4>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery (necessary for Bootstrap\'s JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>
<script type="text/javascript" charset="utf8" src="//cdn.datatables.net/1.10.11/js/jquery.dataTables.js"></script>

<style type="text/css">

.breadcrumb a{
    cursor: pointer;
}

</style>


<script type="text/javascript">
    $(function() {
        function processData(data){
            $("#loading").hide();
            $("#title").html(data.title);
            var formBody = $("#formBody");
            formBody.html('');
            if(data.flash){
                if(data.flash.danger)
                    formBody.append(printAlert('danger', data.flash.danger));
                if(data.flash.info)
                    formBody.append(printAlert('info', data.flash.info));
                if(data.flash.success)
                    formBody.append(printAlert('success', data.flash.success));
            }
            if(data.simpleData){
                $("#simpledata").html("");
                $("#simpledata").append(data.simpleData+'<br>');
            }
            $breadcrumb = $(".breadcrumb");
            if(data.breadcrumb) {
                $breadcrumb.html("");
                for (var index = 0; index < data.breadcrumb.length; ++index) {
                    $breadcrumb.append('<li><a id="' + data.breadcrumb[index].link + '">' + data.breadcrumb[index].label);
                }
                $breadcrumb.append('<li class="active">' + data.title);
            }
            if(data.form){
                //formBody.append('<form/>');
                $form = $('<form id="#form" method="post"></form>');
                for (var index = 0; index < data.formData.length; ++index) {
                    var field = data.formData[index];
                    //formBody.append('<div class="form-group">');
                    if(field.type=="radio")
                    {
                        $formElement = $('<div class="radio">');
                        $formElement.append('<label><input type="'+field.type+'" name="'+field.name+'" value="'+field.value+'">'+field.label+'</label>');
                        $form.append($formElement);
                    } else {
                        $formElement = $('<div class="form-group">');
                        if(field.label)
                            $formElement.append('<label for="'+field.name+'">'+field.label+'</label>');
                        $formElement.append('<input class="form-control" type="'+field.type+'" name="'+field.name+'" value="'+field.value+'"></div>');
                        $form.append($formElement);
                    }
                }
                //formBody.append('</form>');
                formBody.append($form);
            }
            if(data.table){
                formBody.append('<table id="dataTable" class="display" style="font-size: 12px;"></table>');
                $('#dataTable').DataTable( {
                    data: data.tableData,
                    columns: data.tableColumns
                } );
            }

        }

        function printAlert(type, msg){
            return '<div class="alert alert-'+type+' alert-dismissible" role="alert">'
                +'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
                +msg+'</div>';
        }

        function makerequest(formdata){
            $("#loading").show();
            $.post( "", formdata, function(data, status, xhr) {
                processData(data);
            }).fail(function(xhr) {
                $("#loading").hide();
                if(xhr.status == 401) {
                    makerequest({link: "/login"});
                }
            });
        }
        $("#formBody").on("submit", "form", function(e){
            e.preventDefault();
            var str = $( "form" ).serialize();
            makerequest(str);
        });
        $(".breadcrumb").on("click", "a", function(e){
            e.preventDefault();
            makerequest({link : $(this).attr("id") });
        });

        function showMyModel(title, data){
            $("#myModalLabel").html(title);
            $(".modal-body").html(data);
            $('#myModal').modal('show');
        }

        $("#quick-search").on("keyup", function(){
            var search = $("#quick-search").val();
            if(search.length<2)
                $("#quick-links").html("");
            else{
                $.post( "", { link: "/quick-search", str : search } )
                    .done(function(data){
                        $("#quick-links").html("");
                        for (var index = 0; index < data.length; ++index) {
                            $("#quick-links").append("<li class=\"list-group-item quick-link-item\" id='"+data[index].link
                                +"'>" + data[index].label);
                        }
                    });
            }

        });

        $("#quick-links").on("click", ".quick-link-item", function(e){
            makerequest({link : $(this).attr("id") });
            $("#quick-links").html("");
            $("#quick-search").val("");
        });

        makerequest({link:"/home"});

    });
</script>
</body>
</html>