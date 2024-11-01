/* --------------------------
Speak About
A plugin for inline blog comments. https://github.com/benreimer9/speak-about
Utilizes the Rangy library for range and selection, MIT License https://github.com/timdown/rangy

https://github.com/timdown/rangy/wiki/

 -------------------------- */
 
(function ($) {
$(document).ready(function () {


// Setup Rangy
//-------------------------------------------
var serializedHighlights = decodeURIComponent(window.location.search.slice(window.location.search.indexOf("=") + 1));
var highlighter;

window.onload = function () {
  rangy.init();
  highlighter = rangy.createHighlighter();

  highlighter.addClassApplier(rangy.createClassApplier("h_item", {
    ignoreWhiteSpace: true,
    elementTagName: "mark",
    elementAttributes : {
      h_id: "default"
    }
  }));

};
//-------------------------------------------




// SETUP 
//-------------------------------------------

var state = {
  items : [
    /* example item
      {
       id:0,
       highlight:"",
       highlightWithContext:"", 
       comment:"",
       visible:false,
       numOfTags:0,
     },
    */
  ],
};

function setupSpeakAbout(){
  setupMobile();

  document.addEventListener('mouseup', () => {
    var highlight = document.getSelection();
    if (isNotJustAClick(highlight)) {
        if (!isMobile()){
            buildNewItem();
        } 
    }
  });
  updatePageSelectionColor();
}

function isNotJustAClick(highlight) {
  return (highlight.anchorOffset !== highlight.focusOffset);
}

function updatePageSelectionColor(){  
  if (typeof highlightColor !== 'undefined') {
    var style = document.createElement('style');
    style.innerHTML = `
      #speakaboutWrapper mark.h_item {
        background-color: ${highlightColor};
      }
      #speakaboutWrapper mark.h_item .h_comment .h_close {
        border-color: ${highlightColor};
      }
      #speakaboutWrapper mark.h_item .h_comment.sa_hidden .h_close {
        background-color: ${highlightColor};
      }
      #speakaboutWrapper ::selection {
          background-color: ${highlightColor} !important;
      }
      #speakaboutWrapper mark.h_item.submitted.h_blend.sa_hidden .h_wrapper .h_submit{
        background-color: ${highlightColor};
      }
    `;
    document.querySelector("body").insertAdjacentElement("afterbegin", style)
  }
}



// NEW ITEM 
//-------------------------------------------
// Building a new item, which can be composed of multiple <mark> tags but one itemId to unify them 
function buildNewItem(){
  if (!highlightIsWithinWrapper()) return;
  highlighter.highlightSelection("h_item", { exclusive: false });
  var newMarkTags = findNewMarkTags();
  var itemId = null;
  newMarkTags.forEach(tag => {
    if (itemId === null){
      itemId = createTagId();
    } 
    addIdToTag(tag, itemId);
    addCommentComponent(tag, itemId);
    addItemToState(tag, itemId);
  });
  removeExtraCommentComponents(itemId);
  removeNestedComments(); //for overlapping highlights
}

function highlightIsWithinWrapper(){
  var highlightEl = window.getSelection().anchorNode.parentNode;
  if ($(highlightEl).closest("#speakaboutWrapper").length === 0){
    return false;
  }
  else {
    return true;
  }
}

function getHighlightEl(tag){
  return highlighter.getHighlightForElement(tag);
}

function findNewMarkTags(){
  // new mark tags do not have h_id attributes 
  var newMarktags = [];
  var allMarkTags = document.querySelectorAll("mark");
  newMarktags = Array.prototype.slice
    .call(allMarkTags)
    .filter(tag => tag.getAttribute('h_id') === "default");
  return newMarktags;
}

function addItemToState(tag, itemId){
  if (itemIsAlreadyInState(itemId)){
    state.items.map(item => {
      if (item.id === itemId) {
        item.numOfTags++;
        item.highlight += tag.innerText;
        item.highlightWithContext = getHighlightTextContext(tag, itemId)
      }
    })
  }
  else {
    state.items.push({
      id: itemId,
      comment:"",
      visible:true,
      numOfTags:1,
      highlight: tag.innerText,
      highlightWithContext: getHighlightTextContext(tag, itemId)
    })
  }
}

function getHighlightTextContext(tag, itemId) {
  //make a separate hidden copy of the highlight and text I can manipulate to get the proper format. Remove after.
  var elem = getHighlightEl(tag);
  var getRange = elem.getRange()
  var parentElement = getRange.commonAncestorContainer.innerHTML;
  var shadowElement = `<div id="SA_SHADOW" style="display:none"></div>`;
  document.querySelector("body").insertAdjacentHTML("beforeend", shadowElement);
  document.querySelector('#SA_SHADOW').innerHTML = parentElement;

  //remove comments from the shadow version
  var shadowComments = document.querySelectorAll(`#SA_SHADOW .h_comment`);
  shadowComments.forEach(el => {
    el.remove();
  });

  //remove scripts from the shadow version
  var shadowScripts = document.querySelectorAll(`#SA_SHADOW script`);
  shadowScripts.forEach(el => {
    el.remove();
  });

  //reformat the highlight with a span and inline CSS so the highlight appears in the email
  var highlightItemsList = document.querySelectorAll(`#SA_SHADOW mark[h_id="${itemId}"]`);
  var startHighlightStyling = `<span class="SA_HIGHLIGHT" style="line-height: 12px; font-size: 16px; margin: 0; padding:3px; background-color:#ffc2c2">`
  var endHighlightStyling = `</span>`;
  var lastHighlightTextLength; //this will be needed later
  highlightItemsList.forEach(item => {
    item.innerText = startHighlightStyling + item.innerText + endHighlightStyling;
    lastHighlightTextLength = item.innerText.length; 
  })

  var plainTextShadow = document.querySelector("#SA_SHADOW").innerText;
  var firstHighlightPos = plainTextShadow.indexOf(`<span class="SA_HIGHLIGHT"`); //finds first highlight
  var lastHighlightPos = plainTextShadow.lastIndexOf(`<span class="SA_HIGHLIGHT"`); //finds last highlight
  var numOfExtraCharactersForContext = 150; 
  var startingDots = "...";
  var endingDots = "...";

  var sliceStartPoint = firstHighlightPos - numOfExtraCharactersForContext;
  if (sliceStartPoint <= 0){
    sliceStartPoint = 0;
    startingDots = "";
  } 
  var sliceEndPoint = lastHighlightPos + lastHighlightTextLength + numOfExtraCharactersForContext;
  if (sliceEndPoint > plainTextShadow.length){
    sliceEndPoint = plainTextShadow.length;
    endingDots = "";
  } 
  var shortenedPlainTextShadow = plainTextShadow.slice(sliceStartPoint, sliceEndPoint);
  var reportHTML = `<p>${startingDots}${shortenedPlainTextShadow}${endingDots}</p>`;

  document.querySelector('#SA_SHADOW').remove();
  return reportHTML;
}

function itemIsAlreadyInState(id){
  var numOfTagsPerId = [];
  numOfTagsPerId = state.items.filter(item => {
    return item.id === id;
  })
  return numOfTagsPerId.length !== 0 
}

function addIdToTag(tag, itemId){
  tag.setAttribute("h_id", itemId);
}

function getItemFromItemId(itemId){
  specifiedItem = {}
  state.items.forEach(item => {
    if (item.id === itemId) {
      specifiedItem = item;
    }
  });
  return specifiedItem;
}

function removeExtraCommentComponents(itemId){
  
  //check number of tags for the Id. If multiple tags, remove all comments but the last 
  var numberOfTags;
  state.items.map( item => {
    if (item.id === itemId) numberOfTags = item.numOfTags;
  })
  
  var commentComponentList; 
  if (numberOfTags > 1){
    commentComponentList = document.querySelectorAll(`mark[h_id = "${itemId}"] .h_wrapper`);
    for (i = 0; i < numberOfTags; i++) {
      if (i != numberOfTags - 1) {
        commentComponentList[i].remove(); 
      }
    }
  }
}



// MOBILE
//-------------------------------------------

function setupMobile(){
  if (isMobile()){
    window.addEventListener("touchend", function(){
      if (window.getSelection().toString()){
        showMobileCommentBtn();
      }
    })
    window.addEventListener("touchstart", function () {
      if (!window.getSelection().toString()) {
        hideMobileCommentBtn();
      }
    })
  }
  // listen for highlights, then call showMobileComment
}

function isMobile(){
  return 'ontouchstart' in window;
}

function showMobileCommentBtn(){

  var commentButtonHTML = "<div id='sa_commentBtn'>Write a comment</div>";
  var commentButton = document.querySelector("#sa_commentBtn");
  var body = document.querySelector("body");

  if (commentButton == null){
    body.insertAdjacentHTML('beforeend', commentButtonHTML);
  }

  document.querySelector("#sa_commentBtn").classList.add('sa_visible');

  document.querySelector("#sa_commentBtn").addEventListener("touchstart", function(){
    mobileComment();
  });
  //Show a "write comment" button 
  //If they click the button, then call buildNewItem()
}

function mobileComment(){
    highlighter.highlightSelection("h_item");
    buildNewItem();


}

function hideMobileCommentBtn(){
  var mobileBtn = document.querySelector("#sa_commentBtn");
  if (mobileBtn) {
    mobileBtn.remove();
  }
}





// COMMENTS
//-------------------------------------------

function commentHTML(){
  const html =
  "<div class='h_wrapper'>" + 
    "<form class='h_comment'>" +
      "<input type='text' name='comment' placeholder='Comment' autocomplete='false'>" + 
    "</form>" +
    "<div class='h_submit'>" + 
      '<svg width="10px" height="10px" viewBox="0 0 13 10" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' +
        '<g id="Version-Three---WP" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' +
            '<g id="Artboard" transform="translate(-1507.000000, -412.000000)" fill="#8E8E8E" fill-rule="nonzero">' +
                '<g id="confirm" transform="translate(1507.000000, 412.000000)">' +
                    '<polygon id="Path" points="3.5456019 10 0 6.66666667 1.18159716 5.55580952 3.5456019 7.77828571 11.8184028 0 13 1.1116"></polygon>' +
                '</g>' +
            '</g>' +
        '</g>' +
      '</svg>' + 
    "</div>" + 
    "<div class='h_cancel'>" + 
      '<svg width="9px" height="2px" viewBox="0 0 12 2" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' +
          '<g id="Version-Three---WP" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">' + 
              '<g id="Artboard" transform="translate(-1507.000000, -460.000000)" fill="#8E8E8E">' + 
                  '<rect id="cancel" x="1507" y="460" width="12" height="2"></rect>' + 
              '</g>' + 
          '</g>' + 
      '</svg>' + 
    "</div>" + 
  "</div>";
  return html;
}

function addCommentComponent(tag, itemId){
  tag.insertAdjacentHTML("beforeend", commentHTML());
  addEventListenersToComment(itemId);
  //document.getSelection().removeAllRanges(); //remove the browser highlight and keep just the CSS one for better UX
}

function addEventListenersToComment(itemId) {

  //submit on enter key
  var itemMarkTags = document.querySelectorAll(`mark[h_id = "${itemId}"]`);
  itemMarkTags.forEach(tag => {
    tag.addEventListener("submit", event => {
      event.preventDefault();
      commentToState(tag, itemId);
      if (tag.classList.contains('submitted')){
        updateComment(itemId);
        closeComment(itemId);
      }
      else {
        submitComment(tag, itemId);
        closeComment(itemId);
      }
    })
  });

  //submit with checkmark button
  var submitButtons = document.querySelectorAll(`mark[h_id = "${itemId}"] .h_submit`);
  submitButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      let tag = btn.closest("mark");
      if (tag.classList.contains('sa_hidden')){
        toggleCommentVisibility(itemId)
      }
      else if (tag.classList.contains('submitted')){
        commentToState(tag, itemId);
        updateComment(itemId);
        closeComment(itemId);
      }
      else {
        commentToState(tag, itemId);
        submitComment(tag, itemId);
        closeComment(itemId);
      }      
    })
  });

  // delete comment
  var deleteButtons = document.querySelectorAll(`mark[h_id = "${itemId}"] .h_cancel`);
  deleteButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      deleteComment(itemId)
    })
  });
}

function closeComment(itemId) {
  setTimeout(() => {
    toggleCommentVisibility(itemId);
    setTimeout(() => {
      addSubmitBtnColor(itemId);
    }, 500)
  }, 500);
}

function toggleCommentVisibility(itemId) {
  state.items.map(item => {
    if (item.id === itemId) {
      item.visible = !item.visible;
      rerenderComponentsVisibility();
    }
  })
}

function addSubmitBtnColor(itemId){
  state.items.map(item => {
    if (item.id === itemId) {
      let tag = document.querySelector(`mark[h_id = "${itemId}"].submitted`);
      tag.classList.add("h_blend");
    }
  })
}

function commentToState(tag, itemId){
  var inputField = tag.querySelector("input");
  inputField.blur();
  var comment = document.querySelector(`mark[h_id = "${itemId}"] input`).value;
  state.items.forEach(item => {
    if (item.id === itemId) {
      item.comment = comment;
    }
  });
}
function submitComment(tag, itemId){
  var comment = document.querySelector(`mark[h_id = "${itemId}"] input`).value;
  if (comment !== "") {
    tag.classList.add("submitted");
    var item = getItemFromItemId(itemId)
    var action = 'addFeedback';
    sendToDatabase(item, action);
  }
}

function updateComment(itemId){
  var item = getItemFromItemId(itemId)
  var action = 'updateFeedback';
  sendToDatabase(item, action);
}

function deleteComment(itemId) {

  //1. remove from state (and database when applicable)
  let newItems = state.items.filter(item => {
    if ( item.id !== itemId ){
      return item;
    }
    else {
        var action = 'deleteFeedback';
        sendToDatabase(item, action);
    }
  });
  state.items = newItems;

  //2. remove from HTML
  let tagsToRemove = document.querySelectorAll(`mark[h_id = "${itemId}"]`);
  let commentsToRemove = document.querySelector(`mark[h_id = "${itemId}"] .h_wrapper`);
  let parentTag = tagsToRemove[0].parentElement;

  commentsToRemove.remove();
  $(tagsToRemove).contents().unwrap();
  parentTag.normalize(); //fix the messed up text nodes back to normal after the unwrap. This is like, half-effective. 

}

function rerenderComponentsVisibility(){
  state.items.map( item => {
    if (item.visible){
      var itemForms = document.querySelectorAll(`mark[h_id = "${item.id}"]`);
      itemForms.forEach(item => {
        item.classList.remove("sa_hidden");
      });
    }
    else {
      var itemForms = document.querySelectorAll(`mark[h_id = "${item.id}"]`);
      itemForms.forEach(item => {
        item.classList.add("sa_hidden");
      });
    }
  })
}

function removeNestedComments(){
  var nested = document.querySelectorAll("mark > mark.h_item");
  nested.forEach(nestItem => {
    var parent = nestItem.parentElement; 
    if (parent.hasAttribute('h_id')){
      //now confirmed the parent mark is indeed a speakabout one and this is nesting
      //1. move any comment over to the new item
      var oldItemId = parent.getAttribute("h_id");
      var newItemId = nestItem.getAttribute("h_id");
      var oldItemComment;
      state.items.forEach(item => {
        if (item.id === oldItemId) {
            oldItemComment = item.comment; 
          }
      });
      state.items.forEach(item => {
        if (item.id === newItemId){
          item.comment = oldItemComment;
          document.querySelector(`mark[h_id = "${newItemId}"] input`).value = oldItemComment;
        }
      });

      //2. remove the old comment html
      $(parent).contents().unwrap();
      var oldCommentHTML = document.querySelector(`mark[h_id = "${oldItemId}"] .h_wrapper`);
      if (oldCommentHTML !== null){
        oldCommentHTML.parentElement.remove()
      }

      //3. remove the old comment in the database
      var item = getItemFromItemId(oldItemId)
      var action = 'deleteFeedback';
      sendToDatabase(item, action);
    }
  });
}


// Sending Report 
//-------------------------------------------

function sendToDatabase(item, action){
  var userId = getUserId();
  var itemId = item.id; 
  var highlight = item.highlight;
  var highlightWithContext = item.highlightWithContext;
  var comment = item.comment;
  var pageName = document.title;
  var pageURL = document.location.href;
  var adminHref = sa_ajax.ajaxurl;  

  //action can be : addFeedback, updateFeedback, deleteFeedback

  var mailData = {
    'action': action,
    'userId': userId,
    'itemId': itemId,
    'highlight': highlight,
    'highlightWithContext': highlightWithContext,
    'comment': comment,
    'pageName': pageName,
    'pageURL': pageURL
  };

  $.post(adminHref, mailData, function (response) {
    //console.log('Response: ', response);
  });
}

function getUserId(){
  if (storageAvailable('localStorage')) {
    var userId = getUserIdFromStorage();
    if (userId) {
      return userId;
    } else {
      return createUserId();
    }
  }
  else {
    return "anonymous";
  }
}
function getUserIdFromStorage() {
  return localStorage.getItem('speakabout_userId');
}
function createUserId() {
  var id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = Math.random() * 16 | 0, v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
  localStorage.setItem('speakabout_userId', id);
  return id;
}
function createTagId() {
  var id = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
    var r = Math.random() * 16 | 0,
      v = c == 'x' ? r : (r & 0x3 | 0x8);
    return v.toString(16);
  });
  return id;
}


  //If I'd like the ability to see highlights in context on an actual page it is possible
  // use rangy serialize https://github.com/timdown/rangy/wiki/Highlighter-Module 



//Rangy Code worth remembering
/* alert(rangy.getSelection());                               -- gets the highlight selection 
var elem = getHighlightEl(tag);                               --- get the element highlight
var fText = elem.getRange().nativeRange.commonAncestorContainer.innerText;  -- gets the parent container text
var rangeStart = elem.characterRange.start;                   -- character ranges
var getRange = elem.getRange()                                -- element range
var parentText = getRange.commonAncestorContainer.innerText;  --- the parent that contains the bigger chunk of text
var highlightText = getRange.endContainer.data;               --- the string of the highlighted text
var startOffset = getRange.startOffset;                       --- the offset of the highlight from the beginning of the element
var start = getRange.startContainer.data;                     --- all the preceding text before highlight in that element 
var startIndex = parentText.indexOf(start)                    --- how far off of parentText our start text begins
var beginningOffset = startIndex + startOffset;
var preHighlightText = parentText.slice(0, beginningOffset)
var postHighlightText = parentText.slice(startOfPostHighlight)
*/


function storageAvailable(type) {
  //check if localstorage is available. From https://developer.mozilla.org/en-US/docs/Web/API/Web_Storage_API/Using_the_Web_Storage_API
  var storage;
  try {
    storage = window[type];
    var x = '__storage_test__';
    storage.setItem(x, x);
    storage.removeItem(x);
    return true;
  } catch (e) {
    return e instanceof DOMException && (
        // everything except Firefox
        e.code === 22 ||
        // Firefox
        e.code === 1014 ||
        // test name field too, because code might not be present
        // everything except Firefox
        e.name === 'QuotaExceededError' ||
        // Firefox
        e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
      // acknowledge QuotaExceededError only if there's something already stored
      (storage && storage.length !== 0);
  }
}

setupSpeakAbout()

});
}(jQuery))