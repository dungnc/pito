<?php

function convert_unicode($string)
{
    return Normalizer::normalize($string, Normalizer::FORM_C);
}
