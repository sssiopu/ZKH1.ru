document.addEventListener('DOMContentLoaded', function () {
  var canvas = document.getElementById('catCanvas');
  if (!canvas) return;

  canvas.width = 20;
  canvas.height = 24;
  canvas.style.width = '60px';
  canvas.style.height = '72px';

  var ctx = canvas.getContext('2d');
  var input = document.getElementById('searchInput');

  var T=null,B='#0d1b4b',W='#ffffff',HB='#3355cc',
      EW='#ddeeff',EB='#2244bb',EP='#4466dd',EH='#99bbff',
      PK='#ffaacc',GL='#4477dd',SD='#99aadd';

  // 20 строк тела + 4 строки хвоста снизу
  // хвост идёт из левого нижнего угла тела, загибается влево-вниз
  // tail_idle:  хвост спокойно свисает влево
  // tail_wag1:  хвост поднят вверх
  // tail_wag2:  хвост опущен вниз

  var TAIL_ROWS = {
    idle: [
      // строки 20-23: хвост в левой части
      [HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, T, HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
    ],
    wag1: [
      // хвост задран вверх — нарисуем через отдельную строку выше
      [T, HB,HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
    ],
    wag2: [
      // хвост опущен вниз
      [HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, T, HB,HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
      [T, T, T, HB,B, T, T, T, T, T, T, T, T, T, T, T, T, T, T, T],
    ]
  };

  // Все фреймы тела 20 строк × 20 колонок
  var BODY = {
    idle: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EP,EW,EW,EB,EP,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EP,EH,EW,EW,EP,EH,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,GL,W,W,GL,W,W,GL,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,GL,GL,GL,GL,GL,GL,GL,GL,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,B,B,GL,GL,B,B,GL,B,T,T,T,T,T,T,T,T]
    ],
    blink: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EB,EW,EW,EB,EB,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,GL,W,W,GL,W,W,GL,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,GL,GL,GL,GL,GL,GL,GL,GL,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,B,B,GL,GL,B,B,GL,B,T,T,T,T,T,T,T,T]
    ],
    look_r: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EB,EP,EW,EW,EB,EP,EW,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EP,EH,EW,EW,EP,EH,EW,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,PK,PK,EW,EW,EW,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,GL,W,W,GL,W,W,GL,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,GL,GL,GL,GL,GL,GL,GL,GL,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,B,B,GL,GL,B,B,GL,B,T,T,T,T,T,T,T,T]
    ],
    look_l: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EP,EB,EW,EW,EP,EB,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EH,EP,EW,EW,EH,EP,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,GL,W,W,GL,W,W,GL,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,GL,GL,GL,GL,GL,GL,GL,GL,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,B,B,GL,GL,B,B,GL,B,T,T,T,T,T,T,T,T]
    ],
    walk1: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EP,EW,EW,EB,EP,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EP,EH,EW,EW,EP,EH,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,W,W,W,GL,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,T,B,GL,GL,GL,B,GL,GL,GL,GL,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,GL,GL,T,T,B,GL,GL,B,T,T,T,T,T,T,T,T]
    ],
    walk2: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EP,EW,EW,EB,EP,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EP,EH,EW,EW,EP,EH,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,GL,W,W,W,GL,W,B,T,T,T,T,T,T,T],
      [T,T,B,GL,GL,GL,B,GL,GL,GL,B,GL,B,T,T,T,T,T,T,T],
      [T,T,T,B,GL,GL,T,T,B,GL,GL,B,T,T,T,T,T,T,T,T]
    ],
    happy: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EH,EH,EW,EW,EH,EH,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EB,EW,EW,EB,EB,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,GL,W,W,GL,W,W,GL,W,W,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,GL,B,GL,GL,B,GL,GL,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,B,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T]
    ],
    search: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EB,EP,EW,EW,EB,EB,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EP,EH,EW,EW,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,W,GL,W,W,GL,W,W,GL,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,GL,GL,GL,GL,GL,GL,GL,GL,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,B,B,GL,GL,B,B,GL,B,T,T,T,T,T,T,T,T]
    ],
    found: [
      [T,T,T,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T,T,T],
      [T,T,T,B,HB,HB,B,HB,HB,B,T,T,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,B,W,W,HB,HB,HB,HB,W,W,W,B,T,T,T,T,T,T,T,T],
      [T,B,W,HB,HB,HB,HB,HB,HB,HB,W,B,T,T,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,EW,EW,EW,EW,HB,HB,B,T,T,T,T,T,T,T],
      [B,HB,W,EB,EP,EH,EW,EB,EP,EH,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EP,EH,EW,EW,EP,EH,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,W,EW,EW,EW,PK,PK,EW,EW,EW,W,HB,B,T,T,T,T,T,T],
      [B,HB,HB,W,W,W,W,W,W,W,HB,HB,HB,B,T,T,T,T,T,T],
      [T,B,HB,HB,HB,HB,HB,HB,HB,HB,HB,B,T,T,T,T,T,T,T,T],
      [T,T,B,W,W,HB,HB,HB,HB,W,W,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,W,W,W,W,W,W,B,T,T,T,T,T,T,T,T,T],
      [T,T,B,SD,SD,SD,SD,SD,SD,SD,SD,B,T,T,T,T,T,T,T,T],
      [T,B,W,W,W,W,W,W,W,W,W,W,B,T,T,T,T,T,T,T],
      [T,B,GL,W,W,GL,W,W,GL,W,W,GL,B,T,T,T,T,T,T,T],
      [T,T,B,GL,GL,B,GL,GL,B,GL,GL,B,T,T,T,T,T,T,T,T],
      [T,T,T,B,B,T,B,B,T,B,B,T,T,T,T,T,T,T,T,T]
    ]
  };

  var S = {IDLE:'idle',FOLLOW:'follow',FOCUS:'focus',TYPING:'typing',
           SEARCHING:'searching',FOUND:'found',HAPPY:'happy',DELAY:'delay'};

  var state=S.IDLE,stateTimer=0,delayAfter=null;
  var tick=0,walkPos=0;
  var blinkTimer=0,isBlinking=false,blinkTick=0;
  var lookTarget=0,lookCurrent=0,lookVel=0;
  var cursorRelX=0;
  var wagActive=false,wagTick=0;

  function transitionTo(ns,delay){
    if(delay){delayAfter=ns;if(state!==S.DELAY){state=S.DELAY;stateTimer=0;}return;}
    state=ns;stateTimer=0;
  }

  function getTail(){
    if(wagActive||state===S.HAPPY||state===S.FOUND){
      var p=Math.floor(tick/6)%3;
      return p===0?TAIL_ROWS.wag1:p===1?TAIL_ROWS.idle:TAIL_ROWS.wag2;
    }
    if(state===S.TYPING){return Math.floor(tick/20)%2?TAIL_ROWS.wag1:TAIL_ROWS.idle;}
    return TAIL_ROWS.idle;
  }

  function drawFrame(bodyF,tailRows){
    ctx.clearRect(0,0,20,24);
    for(var r=0;r<20;r++)for(var c=0;c<20;c++){
      var col=bodyF[r]&&bodyF[r][c];
      if(col){ctx.fillStyle=col;ctx.fillRect(c,r,1,1);}
    }
    for(var i=0;i<4;i++){
      var row=tailRows[i];
      for(var c2=0;c2<20;c2++){
        var col2=row&&row[c2];
        if(col2){ctx.fillStyle=col2;ctx.fillRect(c2,20+i,1,1);}
      }
    }
  }

  function drawHybrid(bodyF,headF,tailRows){
    ctx.clearRect(0,0,20,24);
    for(var r=0;r<20;r++){
      var src=r<12?headF:bodyF;
      for(var c=0;c<20;c++){
        var col=src[r]&&src[r][c];
        if(col){ctx.fillStyle=col;ctx.fillRect(c,r,1,1);}
      }
    }
    for(var i=0;i<4;i++){
      var row=tailRows[i];
      for(var c2=0;c2<20;c2++){
        var col2=row&&row[c2];
        if(col2){ctx.fillStyle=col2;ctx.fillRect(c2,20+i,1,1);}
      }
    }
  }

  document.addEventListener('mousemove',function(e){
    var rect=canvas.getBoundingClientRect();
    cursorRelX=Math.max(-1,Math.min(1,(e.clientX-rect.left-rect.width/2)/200));
    if(state===S.IDLE)transitionTo(S.FOLLOW);
  });
  var mouseTimer=null;
  document.addEventListener('mousemove',function(){
    clearTimeout(mouseTimer);
    mouseTimer=setTimeout(function(){if(state===S.FOLLOW)transitionTo(S.IDLE,500);},1500);
  });
  canvas.addEventListener('click',function(){
    wagActive=true; wagTick=0;
    transitionTo(S.HAPPY);
    openChat();
    setTimeout(function(){ wagActive=false; if(state===S.HAPPY) transitionTo(S.IDLE); }, 800);
  });
  if(input){
    input.addEventListener('focus',function(){lookTarget=0.8;transitionTo(S.FOCUS);});
    input.addEventListener('input',function(){
      if(input.value.length>0){if(state!==S.TYPING)transitionTo(S.TYPING);}
      else transitionTo(S.FOCUS);
    });
    input.addEventListener('blur',function(){
      if(!input.value.length&&state!==S.FOUND&&state!==S.HAPPY){lookTarget=0;transitionTo(S.IDLE,800);}
    });
  }

  function animate(){
    tick++;stateTimer++;
    if(wagActive){wagTick++;if(wagTick>40){wagActive=false;wagTick=0;}}

    if(state===S.FOLLOW)lookTarget=cursorRelX;
    else if(state===S.FOCUS||state===S.TYPING||state===S.SEARCHING)lookTarget=0.8;
    else if(state===S.IDLE||state===S.DELAY)lookTarget=0;

    lookVel+=(lookTarget-lookCurrent)*0.08;
    lookVel*=0.75;
    lookCurrent+=lookVel;

    var gaze=BODY.idle;
    if(lookCurrent<-0.35)gaze=BODY.look_l;
    else if(lookCurrent>0.35)gaze=BODY.look_r;

    if(state===S.IDLE||state===S.FOLLOW||state===S.DELAY){
      blinkTimer++;
      if(!isBlinking&&blinkTimer>120+(Math.random()*80|0)){isBlinking=true;blinkTick=0;blinkTimer=0;}
      if(isBlinking){blinkTick++;if(blinkTick<5)gaze=BODY.blink;else isBlinking=false;}
    }

    var tail=getTail();

    if(state===S.IDLE){
      canvas.style.transform='scale('+(1+Math.sin(tick*0.04)*0.015)+')';
      drawFrame(gaze,tail);
    } else if(state===S.FOLLOW){
      canvas.style.transform='scale(1)';
      drawFrame(gaze,tail);
    } else if(state===S.DELAY){
      var w=Math.sin(tick*0.08)*0.3;
      drawFrame(w<-0.1?BODY.look_l:w>0.1?BODY.look_r:BODY.idle,tail);
      canvas.style.transform='scale(1)';
      if(stateTimer>50&&delayAfter){transitionTo(delayAfter);delayAfter=null;}
    } else if(state===S.FOCUS){
      canvas.style.transform='scaleX(1.04) translateX(2px)';
      drawFrame(BODY.look_r,tail);
    } else if(state===S.TYPING){
      if(tick%22===0)walkPos++;
      var b=Math.abs(Math.sin(tick*0.18))*0.04;
      canvas.style.transform='scaleX(1.03) translateY('+(-b*8)+'px)';
      if(tick%11===0){var wf=['idle','walk1','walk2','walk1'][walkPos%4];drawHybrid(BODY[wf]||BODY.idle,BODY.look_r,tail);}
    } else if(state===S.SEARCHING){
      if(tick%18===0)walkPos++;
      canvas.style.transform='scale(1)';
      drawFrame((Math.floor(tick/18))%2?BODY.search:BODY.look_r,tail);
    } else if(state===S.FOUND){
      canvas.style.transform='scale('+(1+Math.abs(Math.sin(tick*0.15))*0.05)+')';
      drawFrame((Math.floor(tick/16))%2?BODY.found:BODY.happy,tail);
    } else if(state===S.HAPPY){
      var j=Math.abs(Math.sin(tick*0.25))*0.06;
      canvas.style.transform='scale('+(1+j)+') translateY('+(-j*10)+'px)';
      drawFrame((Math.floor(tick/13))%2?BODY.happy:BODY.found,tail);
    }
    requestAnimationFrame(animate);
  }

  drawFrame(BODY.idle,TAIL_ROWS.idle);
  animate();

  window.catSetSearching=function(){transitionTo(S.SEARCHING);};
  window.catSetFound=function(){transitionTo(S.FOUND);};
  window.catSetIdle=function(){transitionTo(S.IDLE,600);};
  window.catWagTail=function(){wagActive=true;wagTick=0;};
});

// ── ЧАТ КОТБОТА ──────────────────────────────────────────────────────────────
var chatHistory = [];
var chatOpen = false;

// Звуки чата УМКА
function playChatSound(type) {
  var AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return;
  try {
    var ctx = new AC();
    var sequences = {
      // Открытие — два коротких восходящих тона, мягко
      open:  [{ f:440, t:0, d:0.1 }, { f:660, t:0.1, d:0.15 }],
      // Закрытие — нисходящий
      close: [{ f:550, t:0, d:0.1 }, { f:370, t:0.09, d:0.12 }],
      // Фокус на поле — лёгкий одиночный «тик»
      focus: [{ f:880, t:0, d:0.06 }],
      // Отправка сообщения — короткий «вжух»
      send:  [{ f:520, t:0, d:0.05 }, { f:780, t:0.05, d:0.08 }],
    };
    var notes = sequences[type] || sequences.focus;
    notes.forEach(function(n) {
      var osc = ctx.createOscillator();
      var gain = ctx.createGain();
      osc.connect(gain); gain.connect(ctx.destination);
      osc.type = 'sine';
      osc.frequency.setValueAtTime(n.f, ctx.currentTime + n.t);
      gain.gain.setValueAtTime(0, ctx.currentTime + n.t);
      gain.gain.linearRampToValueAtTime(0.14, ctx.currentTime + n.t + 0.01);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + n.t + n.d + 0.1);
      osc.start(ctx.currentTime + n.t);
      osc.stop(ctx.currentTime + n.t + n.d + 0.15);
    });
  } catch(e) {}
}

var SYSTEM_PROMPT = 'Ты УМКА — дружелюбный помощник сайта ДомУчет (система управления ЖКХ). Отвечай коротко и по делу на русском языке. Помогай с вопросами про: передачу показаний счётчиков, оплату квитанций, создание заявок на ремонт, личный кабинет, тарифы. Если вопрос не по теме ЖКХ — мягко направляй к теме сайта. Не используй markdown. Отвечай тепло, как помощник-кот.';

function openChat() {
  var chat = document.getElementById('catChat');
  if (!chat) return;

  // Звук открытия чата — восходящий мягкий аккорд
  playChatSound('open');

  var starsEl = document.getElementById('chatStars');
  if (starsEl && !starsEl.dataset.built) {
    starsEl.dataset.built = '1';
    for (var i = 0; i < 80; i++) {
      var s = document.createElement('div');
      s.className = 'chat-star';
      var sz = Math.random() < 0.3 ? (Math.random()*3+3) : (Math.random()*2+1);
      s.style.cssText = 'width:'+sz+'px;height:'+sz+'px;left:'+(Math.random()*100)+'%;top:'+(Math.random()*85)+'%;--d:'+(2+Math.random()*4)+'s;--delay:'+(Math.random()*4)+'s';
      starsEl.appendChild(s);
    }
  }
  chat.classList.add('open');
  chatOpen = true;
  mirrorCatToMascot();

  // показываем подсказки один раз
  var msgs = document.getElementById('chatMessages');
  if (msgs && !msgs.dataset.hintsBuilt) {
    msgs.dataset.hintsBuilt = '1';
    var hints = [
      'Как передать показания?',
      'Как оплатить квитанцию?',
      'Создать заявку на ремонт',
      'Забыл пароль',
      'Узнать тарифы',
      'Контакты УК',
      'Статус моей заявки',
      'Что такое капремонт?',
    ];
    var wrap = document.createElement('div');
    wrap.className = 'chat-hints';
    wrap.id = 'chatHints';
    hints.forEach(function(h) {
      var btn = document.createElement('button');
      btn.className = 'chat-hint';
      btn.textContent = h;
      btn.onclick = function() { sendHint(h); };
      wrap.appendChild(btn);
    });
    msgs.appendChild(wrap);
    msgs.scrollTop = msgs.scrollHeight;
  }

  setTimeout(function(){
    var inp = document.getElementById('chatInput');
    if (inp) {
      inp.focus();
      // Звук при фокусе на поле — один раз при открытии
      if (!inp.dataset.soundBound) {
        inp.dataset.soundBound = '1';
        inp.addEventListener('focus', function() { playChatSound('focus'); });
      }
    }
  }, 300);
}

function closeChat() {
  var chat = document.getElementById('catChat');
  if (chat) chat.classList.remove('open');
  playChatSound('close');
  chatOpen = false;
}

function mirrorCatToMascot() {
  var src = document.getElementById('catCanvas');
  var dst = document.getElementById('chatMascotCanvas');
  if (src && dst) { dst.getContext('2d').clearRect(0,0,20,24); dst.getContext('2d').drawImage(src,0,0); }
  if (chatOpen) requestAnimationFrame(mirrorCatToMascot);
}

function addMessage(role, text) {
  var msgs = document.getElementById('chatMessages');
  if (!msgs) return;
  var div = document.createElement('div');
  div.className = 'chat-msg ' + role;
  if (role === 'user') {
    var av = document.createElement('div');
    av.className = 'chat-av user-av';
    av.textContent = 'Вы';
    div.appendChild(av);
  }
  var bubble = document.createElement('div');
  bubble.className = 'chat-bubble';
  bubble.textContent = text;
  div.appendChild(bubble);
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
}

function showTyping() {
  var msgs = document.getElementById('chatMessages');
  if (!msgs) return;
  var div = document.createElement('div');
  div.className = 'chat-msg bot';
  div.id = 'typingIndicator';
  var typing = document.createElement('div');
  typing.className = 'chat-bubble chat-typing';
  typing.innerHTML = '<span></span><span></span><span></span>';
  div.appendChild(typing);
  msgs.appendChild(div);
  msgs.scrollTop = msgs.scrollHeight;
}

function removeTyping() {
  var el = document.getElementById('typingIndicator');
  if (el) el.remove();
}

function setChatStatus(txt) {}

function sendHint(text) {
  var hints = document.getElementById('chatHints');
  if (hints) hints.remove();
  addMessage('user', text);
  var sendBtn = document.getElementById('chatSend');
  if (sendBtn) sendBtn.disabled = true;
  showTyping();
  setTimeout(function() {
    removeTyping();
    addMessage('bot', getBotReply(text));
    if (sendBtn) sendBtn.disabled = false;
  }, 600 + Math.random() * 400);
}

function sendChatMessage() {
  var inp = document.getElementById('chatInput');
  var sendBtn = document.getElementById('chatSend');
  if (!inp || !inp.value.trim()) return;
  var text = inp.value.trim();
  inp.value = '';
  playChatSound('send');
  // скрываем подсказки при первом сообщении
  var hints = document.getElementById('chatHints');
  if (hints) hints.remove();
  addMessage('user', text);
  if (sendBtn) sendBtn.disabled = true;
  showTyping();
  setTimeout(function () {
    removeTyping();
    addMessage('bot', getBotReply(text));
    if (sendBtn) sendBtn.disabled = false;
  }, 600 + Math.random() * 500);
}

// ── БАЗА ЗНАНИЙ ───────────────────────────────────────────────────────────────
var KB = [
  // Показания счётчиков
  { keys: ['показани','счётчик','счетчик','передать','водоснабж','вода','газ','электр','свет','тепло'],
    answer: 'Чтобы передать показания счётчиков:\n1. Войдите в личный кабинет\n2. Нажмите «Счётчики» в меню\n3. Введите текущие показания по каждому прибору\n4. Нажмите «Отправить»\n\nПоказания принимаются с 15 по 25 число каждого месяца.' },

  // Квитанции и оплата
  { keys: ['квитанци','оплат','платёж','платеж','задолженност','долг','начислени','счёт','счет'],
    answer: 'Для оплаты квитанций:\n1. Перейдите в раздел «Квитанции»\n2. Выберите нужный период\n3. Нажмите «Оплатить»\n\nИстория всех платежей хранится в личном кабинете. Если есть задолженность — она выделена красным.' },

  // Заявки на ремонт
  { keys: ['заявк','ремонт','поломк','сломал','не работает','авари','авария','засор','течь','течет','протечк'],
    answer: 'Чтобы подать заявку на ремонт:\n1. Откройте раздел «Заявки»\n2. Нажмите «Создать заявку»\n3. Опишите проблему и укажите квартиру\n4. Отправьте заявку\n\nСтатус заявки можно отслеживать в личном кабинете. Срок рассмотрения — до 3 рабочих дней.' },

  // Статус заявки
  { keys: ['статус', 'рассматрива', 'когда приедут', 'отслеживать', 'выполнена', 'в работе'],
    answer: 'Статусы заявок:\n• Новая — заявка принята\n• В работе — мастер назначен\n• Выполнено — работы завершены\n• Отменено — заявка отклонена\n\nОтслеживать статус можно в разделе «Заявки» → выберите нужную заявку.' },

  // Регистрация и вход
  { keys: ['регистраци','войти','вход','логин','пароль','забыл','аккаунт','личный кабинет'],
    answer: 'Для входа в личный кабинет:\n• Нажмите кнопку «Войти» в правом верхнем углу\n• Введите email и пароль\n\nЕсли забыли пароль — обратитесь к администратору управляющей компании, он сбросит пароль через панель управления.' },

  // Тарифы
  { keys: ['тариф','тарифы','стоимость','цена','сколько стоит','рублей','руб'],
    answer: 'Актуальные тарифы можно посмотреть в разделе «Тарифы» на главной странице.\n\nТарифы устанавливаются управляющей компанией и могут меняться. При изменении тарифа вы получите уведомление.' },

  // Профиль
  { keys: ['профил','данные','фото','аватар','имя','телефон','изменить','редактировать'],
    answer: 'Для изменения личных данных:\n1. Нажмите на своё имя или аватар в верхнем меню\n2. Выберите «Профиль»\n3. Отредактируйте нужные поля\n4. Сохраните изменения' },

  // Квартира
  { keys: ['квартир','адрес','площадь','этаж','номер квартиры','собственник'],
    answer: 'Информация о вашей квартире (адрес, площадь, этаж) отображается в разделе «Профиль».\n\nЕсли данные неверны — обратитесь к администратору управляющей компании для исправления.' },

  // Капремонт
  { keys: ['капремонт','капитальный','фонд','взнос'],
    answer: 'Взносы на капитальный ремонт начисляются ежемесячно согласно установленному тарифу.\n\nИнформация о начислениях отображается в разделе «Квитанции». По вопросам программы капремонта обращайтесь в управляющую компанию.' },

  // Управляющая компания
  { keys: ['управляющ','ук','компани','контакт','телефон ук','диспетчер','связаться'],
    answer: 'Контакты управляющей компании указаны в разделе «О системе» на главной странице.\n\nДля срочных вопросов (аварии, отключения) звоните напрямую в диспетчерскую службу.' },

  // Техподдержка сайта
  { keys: ['не работает сайт','ошибка','баг','не загружается','не могу войти','проблема с сайтом'],
    answer: 'Если возникла техническая проблема с сайтом:\n• Попробуйте обновить страницу (F5)\n• Очистите кэш браузера\n• Попробуйте другой браузер\n\nЕсли проблема не решилась — сообщите администратору.' },

  // Приветствие
  { keys: ['привет','здравствуй','добрый','хай','hello','hi'],
    answer: 'Привет! Я УМКА — помощник по сайту ДомУчет 🐱\n\nМогу помочь с:\n• Передачей показаний счётчиков\n• Оплатой квитанций\n• Созданием заявок на ремонт\n• Вопросами по личному кабинету\n\nЧто вас интересует?' },

  // Спасибо
  { keys: ['спасибо','благодар','пасиб','thanks'],
    answer: 'Пожалуйста! Если возникнут ещё вопросы — всегда рад помочь 🐱' },

  // Пока
  { keys: ['пока','до свидания','bye','всё','все'],
    answer: 'До свидания! Если понадоблюсь — нажмите на меня 🐱' },
];

function getBotReply(text) {
  var q = text.toLowerCase();
  var bestMatch = null;
  var bestScore = 0;

  for (var i = 0; i < KB.length; i++) {
    var score = 0;
    for (var j = 0; j < KB[i].keys.length; j++) {
      if (q.indexOf(KB[i].keys[j]) !== -1) {
        score += KB[i].keys[j].length; // длиннее слово = больше очков
      }
    }
    if (score > bestScore) {
      bestScore = score;
      bestMatch = KB[i];
    }
  }

  if (bestMatch && bestScore > 0) return bestMatch.answer;

  // Ничего не найдено
  var fallbacks = [
    'Не совсем понял вопрос 🤔 Попробуйте спросить по-другому.\n\nЯ могу помочь с: показаниями счётчиков, квитанциями, заявками на ремонт, входом в личный кабинет.',
    'Хмм, на этот вопрос я пока не знаю ответа 🐱 Уточните, пожалуйста — возможно, это касается счётчиков, квитанций или заявок?',
    'По этой теме лучше обратиться напрямую в управляющую компанию. Я помогаю с вопросами по работе сайта ДомУчет.',
  ];
  return fallbacks[Math.floor(Math.random() * fallbacks.length)];
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && chatOpen) closeChat();
});

document.addEventListener('DOMContentLoaded', function () {
  var inp = document.getElementById('chatInput');
  if (inp) inp.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') sendChatMessage();
  });
});
