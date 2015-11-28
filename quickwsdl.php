<?php
/*
 *  NAME   :    quickwsdl.php
 *  CREATED:    2014-08-24
 *  AUTHOR :    James Clifford <jclifford0251+scws@hotmail.com>
 *  COMMENT:    This Class will take in a class name (as a string) and using the
 *              Reflection library dynamically create a WSDL (as XML) and send it.
 *              Only public methods will be exported, unless they take one parameter
 *              named '$header' which will indicate that it is not part of the soap body
 *
 *  NOTE   :    This is meant to help DEVELOPEMENT, do NOT use in PRODUCTION!
 */

class quickwsdl {
    protected $name; //the name of the class 
    protected $oper; //the methods that can be called
    protected $msgs; //the methods parameters types
    protected $rets; //the methods return types
    protected $ctes; //complex type elements

    function setClass($ClassName) {
        $this->name = $ClassName;
        $this->oper = array();
        $this->msgs = array();
        $this->rets = array();
        $this->ctes = array();

        $class = new ReflectionClass($ClassName);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach($methods as $m) {
            if(!($m->isStatic()
            ||   $m->isAbstract()
            ||   $m->isConstructor()
            ||   $m->isDestructor()
            )) {
                $params = $m->getParameters();
                $reqcnt = $m->getNumberOfRequiredParameters();
            

                /*
                    *  NOTE: if a member function only has one parameter called 'header'
                    *        then we assume it is part of the SOAP header and not the body
                    */
                if(!(
                    (count($params) == 1)
                && ($params[0]->name == 'header')
                )) {
                    $name = $m->name;
                    
                    $oIn = new stdClass();
                    $oIn->name = 'return';
                    $oIn->type = 'xsd:string';
                    $oIn->mand = TRUE;

                    $i = 0;
                    $aOut = array();
                    foreach($params as $p) {
                        $oOut = new stdClass();
                        $oOut->name = $p->name;
                        $oOut->type = 'xsd:string';
                        $oOut->mand = TRUE;
                        if($i > $reqcnt) {
                            $oOut->mand = FALSE;
                        }
                        $aOut[$oOut->name] = $oOut;
                        $i++;
                    }

                    $this->oper[] = $name;
                    $this->rets[$name] = $oIn;
                    $this->msgs[$name] = $aOut;
                }
            }
        }
    }

    protected function addComplexType($ClassName, $TypeArray = NULL) {
        $class = new ReflectionClass($ClassName);
        $props = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        
        $part = array();
        foreach($props as $p) {
            $tmp = new stdClass();
            $tmp->name = $p->getName();
            $tmp->type = isset($TypeArray[$tmp->name]) ? $TypeArray[$tmp->name] : 'xsd:string';
            $part[$tmp->name] = $tmp;
        }

        $this->ctes[$ClassName] = $part;
    }

    public function setRequest($MethodName, $ParameterName, $ClassName, $TypeArray = NULL) {
        if(!array_key_exists($ClassName,$this->ctes)) {
            $this->addComplexType($ClassName, $TypeArray);
        }
        $this->msgs[$MethodName][$ParameterName]->type = "tns:$ClassName";
    }
    
    public function setResponse($MethodName, $ClassName, $TypeArray = NULL) {
        if(!array_key_exists($ClassName,$this->ctes)) {
            $this->addComplexType($ClassName, $TypeArray);
        }
        $this->rets[$MethodName]->type = "tns:$ClassName";;
    }

    public function wsdl() {
        /************************
        ** SETUP XML
        ************************/
        $uri = sprintf("http://%s/api/soap",$_SERVER['HTTP_HOST']);
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(TRUE);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElementNS('wsdl','definitions', 'http://schemas.xmlsoap.org/wsdl/');

        $xml->writeAttributeNS('xmlns', 'soap', NULL, "http://schemas.xmlsoap.org/wsdl/soap/");
        #$xml->writeAttributeNS('xmlns', 'schema', NULL, $uri);
        $xml->writeAttributeNS('xmlns', 'xsd', NULL, "http://www.w3.org/2001/XMLSchema");
        $xml->writeAttributeNS('xmlns', 'xsi', NULL, "http://www.w3.org/2001/XMLSchema-instance");
        $xml->writeAttributeNS('xmlns', 'tns', NULL, "$uri");
        $xml->writeAttribute('targetNamespace',"$uri");
        #$xml->writeAttribute('name', $this->name);

        $xml->startElementNS('wsdl','documentation',NULL);
        $xml->text(sprintf('Auto Generated WSDL For %s Class By Quick_WSDL',$this->name));
        $xml->endElement();

        /************************
        ** TYPES
        ************************/

        $xml->startElementNS('wsdl','types',NULL);

        $xml->startElementNS('xsd','schema',NULL);
        $xml->writeAttribute('targetNamespace',"$uri");
        foreach(array_keys($this->ctes) as $n) {
            $xml->startElementNS('xsd','element',NULL);
            $xml->writeAttribute('name', $n);
                $xml->startElementNS('xsd','complexType',NULL);
                    $xml->startElementNS('xsd','sequence',NULL);
                    foreach($this->ctes[$n] as $p) {
                        $xml->startElementNS('xsd','element',NULL);
                        $xml->writeAttribute('name', $p->name);
                        $xml->writeAttribute('type', $p->type);
                        if($p->mand) {
                            $xml->writeAttribute('minOccurs', '1');
                            $xml->writeAttribute('maxOccurs', '1');
                        } else {
                            $xml->writeAttribute('minOccurs', '0');
                            $xml->writeAttribute('maxOccurs', '1');
                        }
                        $xml->endElement(); //element
                    }
                    $xml->endElement(); //sequence
                $xml->endElement(); //complexType
            $xml->endElement(); //element
        }

        foreach($this->oper as $o) {
            if(!isset($this->ctes[$o])) {
                $xml->startElementNS('xsd','element',NULL);
                $xml->writeAttribute('name',sprintf('%s', $o));
                    $xml->startElementNS('xsd','complexType',NULL);
                        $xml->startElementNS('xsd','sequence',NULL);
                        foreach($this->msgs[$o] as $p) {
                            $xml->startElementNS('xsd','element',NULL);
                            $xml->writeAttribute('name',$p->name);
                            $xml->writeAttribute('type',$p->type);
                            if($p->mand) {
                                $xml->writeAttribute('minOccurs', '1');
                                $xml->writeAttribute('maxOccurs', '1');
                            } else {
                                $xml->writeAttribute('minOccurs', '0');
                                $xml->writeAttribute('maxOccurs', '1');
                            }
                            $xml->endElement();
                        }
                        $xml->endElement(); //sequence
                    $xml->endElement(); //complexType
                $xml->endElement(); //element
            }

            if(!isset($this->ctes[sprintf('%sResponse', $o)])) {
                $xml->startElementNS('xsd','element',NULL);
                $xml->writeAttribute('name',sprintf('%sResponse', $o));
                    $xml->startElementNS('xsd','complexType',NULL);
                        $xml->startElementNS('xsd','sequence',NULL);
                            $xml->startElementNS('xsd','element',NULL);
                            $xml->writeAttribute('name',$this->rets[$o]->name);
                            $xml->writeAttribute('type',$this->rets[$o]->type);
                            if($p->mand) {
                                $xml->writeAttribute('minOccurs', '1');
                                $xml->writeAttribute('maxOccurs', '1');
                            } else {
                                $xml->writeAttribute('minOccurs', '0');
                                $xml->writeAttribute('maxOccurs', '1');
                            }
                            $xml->endElement();
                        $xml->endElement(); //sequence
                    $xml->endElement(); //complexType
                $xml->endElement();//message
            }
        }


        $xml->endElement(); //schema

        $xml->endElement(); //types
    
        /************************
        ** MESSAGES
        ************************/

        foreach($this->oper as $o) {
            $xml->startElementNS('wsdl','message',NULL);
            $xml->writeAttribute('name',sprintf('%sIn', $o));
                $xml->startElementNS('wsdl','part',NULL);
                $xml->writeAttribute('name','parameters');
                $xml->writeAttribute('element',sprintf('tns:%s', $o));  
                $xml->endElement();
            $xml->endElement();//message

            $xml->startElementNS('wsdl','message',NULL);
            $xml->writeAttribute('name',sprintf('%sOut', $o));
                $xml->startElementNS('wsdl','part',NULL);
                $xml->writeAttribute('name','parameters');
                $xml->writeAttribute('element',sprintf('tns:%sResponse', $o));
                $xml->endElement();
            $xml->endElement();//message
        }
    
        /************************
        ** PORTTYPE
        ************************/

        $xml->startElementNS('wsdl','portType',NULL);
        $xml->writeAttribute('name',sprintf('Class_%s', $this->name));

        foreach($this->oper as $o) {
            $xml->startElementNS('wsdl','operation',NULL);
            $xml->writeAttribute('name',$o);

            $xml->startElementNS('wsdl','documentation',NULL);
            $xml->text('TODO: Add Documentation');
            $xml->endElement();

            $xml->startElementNS('wsdl','input',NULL);
            $xml->writeAttribute('message',sprintf('tns:%sIn', $o));
            $xml->endElement();

            $xml->startElementNS('wsdl','output',NULL);
            $xml->writeAttribute('message',sprintf('tns:%sOut', $o));
            $xml->endElement();

            $xml->endElement(); //operation
        }
        $xml->endElement(); //portType

        /************************
        ** BINDING
        ************************/

        $xml->startElementNS('wsdl','binding',NULL);
        $xml->writeAttribute('name',sprintf('Bind_%s', $this->name));
        $xml->writeAttribute('type',sprintf('tns:Class_%s', $this->name));

        $xml->startElementNS('soap','binding',NULL);
        $xml->writeAttribute('style','document');
        $xml->writeAttribute('transport','http://schemas.xmlsoap.org/soap/http');
        //$xml->writeAttribute('namespace', "$uri");
        $xml->endElement();

        foreach($this->oper as $o) {
            $xml->startElementNS('wsdl','operation',NULL);
            $xml->writeAttribute('name',$o);

            $xml->startElementNS('soap','operation',NULL);
                $xml->writeAttribute('soapAction',"$uri/$o");
            $xml->endElement();

            $xml->startElementNS('wsdl','input',NULL);
                $xml->startElementNS('soap','body',NULL);
                $xml->writeAttribute('use','literal');
                //$xml->writeAttribute('namespace',"$uri/$o");
                $xml->endElement();
            $xml->endElement();

            $xml->startElementNS('wsdl','output',NULL);
                $xml->startElementNS('soap','body',NULL);
                $xml->writeAttribute('use','literal');
                $xml->endElement();
            $xml->endElement();

            $xml->endElement(); //wsdl:operation
        }

        $xml->endElement(); //wsdl:binding

        /************************
        ** SERVICE
        ************************/

        $xml->startElementNS('wsdl','service',NULL);
        $xml->writeAttribute('name', $this->name);

        $xml->startElementNS('wsdl','port',NULL);
        $xml->writeAttribute('name', sprintf('%sEndpoint', $this->name));
        $xml->writeAttribute('binding', sprintf('tns:Bind_%s', $this->name));

            $xml->startElementNS('soap','address',NULL);
            $xml->writeAttribute('location',"$uri/index.php");
            $xml->endElement();

        $xml->endElement(); //port
        $xml->endElement(); //service

        /************************
        ** CLEANUP
        ************************/

        $xml->endElement(); //definitions
        $xml->endDocument();
    
        header('Content-type: text/xml; charset=UTF-8');
        echo $xml->outputMemory(TRUE);
    }

    public function html() {
        $uri = sprintf("http://%s/api/soap",$_SERVER['HTTP_HOST']);
        $html = new XMLWriter();
        $html->openMemory();
        $html->setIndent(TRUE);
        $html->startDTD('html', '-//W3C//DTD XHTML 1.0 Strict//EN','http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'); // standards compliant
        $html->endDTD(); 

        $html->startElement('html');
        $html->writeAttribute('xmlns','http://www.w3.org/1999/xhtml');

        $html->startElement('head');
        $html->writeElement('title',sprintf('%s Soap Definition', $this->name));
        $html->endElement(); //head

        $html->startElement('body');
            $html->writeElement('h1',sprintf('%s Soap Definition', $this->name));
            $html->startElement('p');
                $html->startElement('a');
                $html->writeAttribute('href',"$uri/?wsdl");
                $html->text('WSDL');
                $html->endElement(); //p
            $html->endElement(); //p


            foreach($this->oper as $o) {
                $html->writeElement('h2',"$o (");
                $html->startElement('p');

                                
                foreach($this->msgs[$o] as $p) {
                    $html->startElement('blockquote');
                    $html->writeElement('code',$p->name);
                    $html->writeElement('i','as');
                    $html->writeElement('i',$p->type);
                    $html->endElement();
                }
                

                $html->text(') {');
                $html->writeElement('br');
                $html->startElement('blockquote');
                $html->text('        ...');
                $html->writeElement('br');



                $html->writeElement('b',$this->rets[$o]->name);
                $html->writeElement('i','as');
                $html->writeElement('i',$this->rets[$o]->type);
                $html->endElement();

                $html->text('}');
                $html->endElement(); //p
            }
            
            $html->writeElement('style','
            code {
                color: blue;
                }
            ');

        $html->endElement(); //body




        $html->endElement(); //html

        header('Content-type: application/xhtml+xml; charset=UTF-8');
        echo $html->outputMemory(TRUE);
    }
}
