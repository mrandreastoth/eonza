<?php

require_once 'ajax_common.php';
require_once APP_EONZA.'lib/files.php';

//print_r( $_POST );
//print_r( $_FILES );
if ( ANSWER::is_success())
{
    if ( !isset( $_POST['idcol']) || !isset( $_POST['iditem']) || empty( $_FILES ) ||
          !isset( $_FILES[ 'file' ] ))
        api_error( 'Wrong parameters', 1 );
    elseif ( $_FILES['file'][ 'error' ] || !is_uploaded_file( $_FILES['file']['tmp_name'] ))
    {
//        The uploaded file exceeds the upload_max_filesize directive in php.ini
        api_error( '$_FILES error: #temp#', $_FILES['file'][ 'error' ] );
    }
       else
    {
        $iditem = (int)$_POST['iditem'];
        $col = $db->getrow("select * from ?n where id=?s", ENZ_COLUMNS, (int)$_POST['idcol'] );
        if ( !$col || !$iditem || ($col['idtype']!= FT_FILE && $col['idtype']!= FT_IMAGE ))
            api_error( 'Wrong parameters', 2 );
        else
        {
            $extend = json_decode( $col['extend'], true );
            $max = $db->getone("select max(`sort`) from ?n where idtable=?s && idcol=?s && iditem=?s", 
                           ENZ_FILES, $col['idtable'], $col['id'], $iditem );
            $idtable = $col['idtable'];
            $idfolder = empty( $extend['storedb'] ) ? files_getfolder( $idtable ) : 0;
            $mime = $_FILES[ 'file' ]['type'];
            $idtype = $db->getone("select id from ?n where name=?s", ENZ_MIMES, strtolower( $mime ));
            if ( !$idtype )
            {
                $idtype = $db->insert( ENZ_MIMES, array( 'name'=>$mime ), '', true );
            }
            if ( $col['idtype'] == FT_IMAGE )
            {
                require_once APP_EONZA."lib/image.php";
                $options = json_decode( $col['extend'], true );
                $image = new Image( $options );
                if ( $image->check( $_FILES['file'] ))
                    $image->original( $_FILES['file']['tmp_name'] );
            }
            if ( ANSWER::is_success() && ANSWER::is_access( A_EDIT, $idtable, $iditem ))
            {
                $idfile = $db->insert( ENZ_FILES, array( '_owner' => GS::userid(),
                   'idtable' => $idtable, 'idcol' => $col['id'], 'iditem' => $iditem,
                   'folder' => $idfolder,
                   'filename' => $_FILES[ 'file' ]['name'],
                   'size' => filesize( $_FILES[ 'file' ]['tmp_name'] ), 'mime' => $idtype,
                   'sort' => $max ? $max + 1 : 1 ), '', true );
                if ( empty( $idfile ))
                    api_error( 'File table error' );
                else
                {
                    if ( ANSWER::is_success())
                    {
                        if ( !$idfolder )
                        {
                            if ( !$db->update( ENZ_FILES, array('storage' => file_get_contents( $_FILES['file']['tmp_name'] )), 
                                   '', $idfile ))
                                api_error( 'Save file to DB' );
                        }
                        else
                        {
            //                    print "<br>".STORAGE.$idtable."/$idfolder/$idfile";
                            if ( !move_uploaded_file( $_FILES['file']['tmp_name'], STORAGE.$idtable."/$idfolder/$idfile" ))
                                api_error( 'err_writefile', STORAGE );
                        }
                    }                    
                    if ( ANSWER::is_success())
                    {
                        if ( $col['idtype'] == FT_IMAGE )
                            $image->finish( $idfile, $idfolder ? STORAGE.$idtable."/$idfolder" : '' );
                        ANSWER::result( files_result( $idtable, $col, $iditem, true ));
                    }
    //                $xresult['result'] = 'File transfer completed';
                }
            }
        }
    }
}
ANSWER::answer();

