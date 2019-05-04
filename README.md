# alldadata
Приложение Dadata для Webasyst

Для использования приложения в своих классах, необходимо подключить приложение:
````php
if(wa()->appExists('alldadata')) {
    wa('alldadata'); 
    $dadata = new alldadataApi(); 
    // дальше можно пользоваться методами класса. Например:
    $alldadata->isBot();    
} 
