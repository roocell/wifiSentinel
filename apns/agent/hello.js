var agent = require('./_header')
  , device = require('../device');



setTimeout(function() {
  console.log('sending msg');
  agent.createMessage()
  .device(device)
  .alert('Hello Universe!')
  .send();
}, 3000);
