<?php
namespace mHttp;

enum Method:string
{
	case GET = 'GET';
	case POST = 'POST';
	case HEADER = 'HEADER';
	case DELETE = 'DELETE';
	case PUT = 'PUT';
}
