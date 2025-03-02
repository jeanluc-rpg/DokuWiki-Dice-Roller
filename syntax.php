<?php

if(!defined('DOKU_INC')) die();

class syntax_plugin_diceroller extends DokuWiki_Syntax_Plugin {
    
    public function getType() {
        return 'substition';
    }
    
    public function getSort() {
        return 32;
    }
    
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{dice .*?}}', $mode, 'plugin_diceroller');
    }
    
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        $match = trim($match, '{}');
        list(, $diceExpression) = explode(' ', $match, 2);
        return [$diceExpression];
    }
    
    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode !== 'xhtml') return false;
        
        $diceExpression = $data[0];
        list($total, $details, $symbols, $error, $hasSW) = $this->evaluateExpression($diceExpression);
        
        if ($error) {
            $renderer->doc .= '⚠️ Erreur : '.$error;
        } else {
            if ($hasSW) {
                $renderer->doc .= '🎲 '.$diceExpression.' = '.$symbols;
            } else {
                $renderer->doc .= '🎲 '.$diceExpression.' = <strong>'.$total.'</strong>'.$details.' '.$symbols;
            }
        }
        return true;
    }
    
    private function evaluateExpression($expression) {
        if (preg_match('/[^0-9dDfFBSPADCNFO+\-*\/()de ]/', $expression)) {
            return [null, '', '', 'Expression invalide.', false];
        }
        
        $symbols = '';
        $hasSW = false;
        
        $sw_dice = [
            'dB' => ['Success', 'Advantage_Success', 'Advantage', 'DoubleAdvantage', 'Neutral', 'Neutral'],
            'dS' => ['Fail', 'Fail', 'Threat', 'Threat', 'Neutral', 'Neutral'],
            'dA' => ['Success', 'Success', 'Double_Success', 'Advantage', 'Advantage', 'DoubleAdvantage', 'Success_Advantage', 'Neutral'],
            'dD' => ['Fail', 'DoubleFail', 'Threat', 'Threat', 'Threat', 'DoubleThreat', 'Fail_Threat', 'Neutral'],
            'dP' => ['Success', 'Success', 'DoubleSuccess', 'DoubleSuccess', 'Advantage', 'Advantage_Success', 'Advantage_Success', 'Advantage_Success', 'DoubleAdvantage', 'Advantage', 'Triumph', 'Neutral'],
            'dC' => ['Fail', 'Fail', 'DoubleFail', 'DoubleFail', 'Threat', 'Threat','Fail_Threat', 'Fail_Threat', 'DoubleThreat', 'DoubleThreat', 'Disaster', 'Neutral'],
            'dF' => ['Light', 'Light', 'Dark', 'Dark', 'Dark', 'Dark', 'Dark', 'Dark', 'DoubleDark', 'DoubleWhite', 'DoubleWhite', 'DoubleWhite']
        ];
        
        $expression = preg_replace_callback('/(\d*)d([BSPADCNFO])/', function($matches) use ($sw_dice, &$symbols, &$hasSW) {
            $numDice = $matches[1] === '' ? 1 : intval($matches[1]);
            $dieType = 'd' . $matches[2];
            if (!isset($sw_dice[$dieType])) {
                return '(0)';
            }
            $hasSW = true;
            for ($i = 0; $i < $numDice; $i++) {
                $roll = $sw_dice[$dieType][rand(0, count($sw_dice[$dieType]) - 1)];
                if ($roll !== '') {
                    $symbols .= ' '.$roll;
                }
            }
            return '(0)';
        }, $expression);
        
        $expression = preg_replace_callback('/(\d*)d(\d+)/i', function($matches) {
            $numDice = $matches[1] === '' ? 1 : intval($matches[1]);
            $numSides = intval($matches[2]);
            if ($numDice <= 0 || $numSides <= 0) {
                return '(0)';
            }
            $rolls = [];
            for ($i = 0; $i < $numDice; $i++) {
                $rolls[] = rand(1, $numSides);
            }
            return '('.implode('+', $rolls).')';
        }, $expression);
        
        $expression = preg_replace_callback('/(\d*)de(\d+)/i', function($matches) {
            $numDice = $matches[1] === '' ? 1 : intval($matches[1]);
            $numSides = intval($matches[2]);
            $rolls = [];
            $total = 0;
            for ($i = 0; $i < $numDice; $i++) {
                $roll = rand(1, $numSides);
                $rolls[] = $roll;
                $total += $roll;
                while ($roll == $numSides) {
                    $roll = rand(1, $numSides);
                    $rolls[] = $roll;
                    $total += $roll;
                }
            }
            return '('.implode('+', $rolls).')';
        }, $expression);
        
        $expression = preg_replace_callback('/(\d*)d[Ff]/i', function($matches) {
            $numDice = $matches[1] === '' ? 1 : intval($matches[1]);
            if ($numDice <= 0) {
                return '(0)';
            }
            $rolls = [];
            $total = 0;  
            for ($i = 0; $i < $numDice; $i++) {
                $roll = rand(-1, 1);  
                $rolls[] = $roll;
                $total += $roll;  
            }
            return '('.implode('+', $rolls).')';
        }, $expression);

        try {
            eval('$total = '.$expression.';');
            return [$total, ' ('.$expression.')', $symbols, null, $hasSW];
        } catch (Throwable $e) {
            return [null, '', '', 'Erreur de calcul.', false];
        }
    }
}

?>