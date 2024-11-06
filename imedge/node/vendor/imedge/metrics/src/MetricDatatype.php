<?php

namespace IMEdge\Metrics;

enum MetricDatatype: string
{
    case GAUGE = 'GAUGE';
    case COUNTER = 'COUNTER';
    case DERIVE = 'DERIVE';
    case DCOUNTER = 'DCOUNTER';
    case DDERIVE = 'DDERIVE';
    case ABSOLUTE = 'ABSOLUTE';
    // case COMPUTE = 'COMPUTE';
}
