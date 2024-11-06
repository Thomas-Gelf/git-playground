<?php

namespace IMEdge\RpcApi;

enum ApiMethodType: string
{
    case REQUEST = 'request';
    case NOTIFICATION = 'notification';
}
