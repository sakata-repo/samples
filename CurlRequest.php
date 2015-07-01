<?php
/**
 * cURL リクエストクラス
 *
 * cURL を使用した、HTTP リクエストクラスです。
 * HTTP GET, POST, PUT, DELETE が可能。
 * 同期リクエストにおける、直列および並列リクエストが可能。
 * 非同期リクエストおよびコールバックの設定は未実装。
 *
 * @package   SakataSocialGameLib
 * @author    Shinichi SAKATA
 * @version   1.0, 2012/04/01
 * @copyright Copyright (C) 2012-
 */

class CurlRequest
{
    // タイムアウト設定
    const CONNECTTIMEOUT    = 4;
    const TIMEOUT           = 4;

    /**
     * リクエスト
     */
    private $_request   = array();

    /**
     * 非同期リクエスト時のコールバック
     */
    private $_callback  = FALSE;
    private $_args      = array();

    /**
     * Initialization
     */
    public function __construct()
    {
    }

    /**
     * リクエストの登録
     *
     * @param  int     $idx        インデックス番号
     * @param  string  $url        URL
     * @param  array   $header     HTTP header @see http://php.net/manual/ja/book.curl.php
     * @param  string  $method     HTTP request method
     * @param  string  $postData   POST or PUT body
     * @param  array   $opt        curl_setopt_array 用の追加オプション
     *
     * @return void
     * @access public
     */
    public function setRequest( $idx = NULL, $url = NULL, $header = array(), $method = 'GET', $postData = NULL, $opt = NULL )
    {
        switch ( strtoupper( $method ) )
        {
            case 'GET':
                $options = array(
                    CURLOPT_HTTPHEADER          => $header,
                    CURLOPT_RETURNTRANSFER      => TRUE,
                    CURLOPT_CONNECTTIMEOUT      => self::CONNECTTIMEOUT,
                    CURLOPT_TIMEOUT             => self::TIMEOUT,
                    CURLOPT_HTTPGET             => TRUE
                );
                break;
            case 'POST':
                //$header  = array_merge( ( array )'Content-Type: multipart/form-data', $header );
                $options = array(
                    CURLOPT_HTTPHEADER          => $header,
                    CURLOPT_RETURNTRANSFER      => TRUE,
                    CURLOPT_CONNECTTIMEOUT      => self::CONNECTTIMEOUT,
                    CURLOPT_TIMEOUT             => self::TIMEOUT,
                    CURLOPT_POST                => TRUE,
                    CURLOPT_POSTFIELDS          => $postData
                );
                break;
            case 'PUT':
                $options = array(
                    CURLOPT_HTTPHEADER          => $header,
                    CURLOPT_RETURNTRANSFER      => TRUE,
                    CURLOPT_CONNECTTIMEOUT      => self::CONNECTTIMEOUT,
                    CURLOPT_TIMEOUT             => self::TIMEOUT,
                    CURLOPT_CUSTOMREQUEST       => 'PUT',
                    CURLOPT_POSTFIELDS          => $postData
                );
                break;
            case 'DELETE':
                $options = array(
                    CURLOPT_HTTPHEADER          => $header,
                    CURLOPT_RETURNTRANSFER      => TRUE,
                    CURLOPT_CONNECTTIMEOUT      => self::CONNECTTIMEOUT,
                    CURLOPT_TIMEOUT             => self::TIMEOUT,
                    CURLOPT_CUSTOMREQUEST       => 'DELETE'
                );
                break;
            default:
                throw new Exception( 'Invalid method.' );
                exit;
        }
        
        if ( is_array( $opt ) )
        {
            $options = array_merge( $options, $opt );
        }
        
        if ( isset( $idx ) )
        {
            $this->_request[ $idx ] = array(
                'idx' => $idx,
                'url' => $url,
                'opt' => $options
            );
        }
        else
        {
            throw new Exception( 'Set idx.' );
        }
    }

    /**
     * 非同期リクエスト用のコールバック設定
     *
     * @return void
     * @access public
     */
    public function setCallback( $callback = FALSE, $args = array() )
    {
        $this->_callback = $callback;
        $this->_args     = $args;
    }
 
    /**
     * 並列リクエストの送信
     *
     * あらかじめ、setRequest にてリクエストを登録しておく必要があります。
     *
     * @return  array   レスポンス結果
     * @access  public
     */
    public function execMultiRequest()
    {
        // マルチハンドルの用意
        $mh = curl_multi_init();

        // URL をキーとして、複数の Curl ハンドルを入れて保持する配列
        $chList = array();

        // Curl ハンドルの用意と、マルチハンドルへの登録
        foreach ( $this->_request as $req )
        {
            $chList[ $req['idx'] ] = curl_init( $req['url'] );
            curl_setopt_array( $chList[ $req['idx'] ], $req['opt'] );

            // キャッシュを使わない場合
            //curl_setopt( $chList[ $req['idx'] ], CURLOPT_FRESH_CONNECT, TRUE );

            // 詳細をログ出力したい場合
            //$fp = fopen( '/tmp/curl' . $req['idx'] . '.log', 'a' );
            //curl_setopt( $chList[ $req['idx'] ], CURLOPT_VERBOSE, 1 );
            //curl_setopt( $chList[ $req['idx'] ], CURLOPT_STDERR, $fp );
            
            curl_multi_add_handle( $mh, $chList[ $req['idx'] ] );
        }

        // 一括で通信実行、全て終わるのを待つ
        $running = null;
        do
        {
            curl_multi_exec( $mh, $running );
        }
        while ( $running );

        // 実行結果の取得
        foreach ( $this->_request as $req )
        {
            // ステータスとコンテンツ内容の取得
            $results[ $req['idx'] ]            = curl_getinfo( $chList[ $req['idx'] ] );
            $results[ $req['idx'] ]['content'] = curl_multi_getcontent( $chList[ $req['idx'] ] );
            
            // Curl ハンドルの後始末
            curl_multi_remove_handle( $mh, $chList[ $req['idx'] ] );
            curl_close( $chList[ $req['idx'] ] );
        }

        // マルチハンドルの後始末
        curl_multi_close( $mh );

        // 非同期リクエスト時の、コールバック設定がある場合
        //if ( $this->_callback )
        //{
        //    call_user_func_array( $this->_callback, $this->_args );
        //}

        // 結果返却
        return $results;
    }

}
?>
