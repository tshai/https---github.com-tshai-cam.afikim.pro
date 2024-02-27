const localVideo = document.getElementById("localVideo");
const remoteVideo = document.getElementById("remoteVideo");
const params = new URLSearchParams(window.location.search);
const user_guid = params.get("user_guid"); // This gets '1' from your example URL
const room_guid = params.get("room_guid");
const user_type = params.get("user_type");
let localStream;
let peerConnection;
let userStartChat = 0;
const config = {
  iceServers: [{ urls: "stun:stun.l.google.com:19302" }], // Google's public STUN server
};

// Initialize WebSocket connection
const socket = new WebSocket("wss://cam.afikim.pro:8080");
$(document).ready(function () {
  // window.onbeforeunload = function (e) {
  //   return "Refresh will kill the chat!";
  // };
  peerConnection = new RTCPeerConnection(config);
  peerConnection.ontrack = function (event) {
    $("#waitingUser").hide();
    $("#localVideo").css("width", "25%");
    console.log("ontrack event");
    remoteVideo.srcObject = event.streams[0];
    remoteVideo.onloadedmetadata = function () {
      remoteVideo.play();
    };
  };
  handleLogin("true", 1, function (x) {
    if (x === "true") {
      console.log("handleLogin");
      $("#startCall").show();
    }
  });
  peerConnection.onicecandidate = function (event) {
    if (event.candidate) {
      socket.send(
        JSON.stringify({
          type: "candidate",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: event.candidate,
        })
      );
    }
  };
  peerConnection.onconnectionstatechange = function (event) {
    switch (peerConnection.connectionState) {
      case "connected":
        console.log("The connection has become fully connected");
        break;
      case "disconnected":
        console.log("The connection disconnected");
        //alert("aa")
        //ReconnectChat();
        break;
      case "failed":
        console.log("The connection failed");
        break;
      case "closed":
        console.log("The connection closed");
        break;
    }
  };
});
socket.addEventListener("open", function (event) {
  // Send identification message

  socket.send(
    JSON.stringify({
      type: "identify",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
    })
  );
});

// Handle WebSocket messages
socket.addEventListener("message", async function (event) {
  const data = JSON.parse(event.data);
  console.log(event.data);
  if (typeof data.error != "undefined") {
    if (data.error === "sessionSts1") {
      alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
    } else if (data.error === "session_finished") {
      alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
    } else if (data.error === "moreThanOneUser") {
      alert("Other user discounected");
    } else if (data.error === "girlDiscounectInternet") {
      alert("Other user discounected");
    } else if (data.error === "finishTime") {
      alert("User time finished");
    }
    window.location.href = "/model-chats/";
  }
  if (typeof data.answer != "undefined") {
    let time_left = data.time_left;
    $("#time_left_user").text("Time left: " + time_left);
    if (time_left <= 30) {
      $("#time_left_user")
        .css("background", "red")
        .css("color", "white")
        .fadeOut(200)
        .fadeIn(200)
        .fadeOut(200)
        .fadeIn(200);
    }
  } else if (data.type === "answer") {
    handleAnswer(data.data);
  } else if (data.type === "startCall") {
    peerConnection.createOffer(
      function (offer) {
        socket.send(
          JSON.stringify({
            type: "offer",
            user_guid: user_guid,
            room_guid: room_guid,
            user_type: user_type,
            data: offer,
          })
        );
        console.log("offer:" + offer);
        peerConnection.setLocalDescription(offer);
      },
      function (error) {
        console.log("Error when creating an offer");
      }
    );
  } else if (data.type === "candidate") {
    handleCandidate(data.data);
    //await peerConnection.addIceCandidate(new RTCIceCandidate(data.data));
  }
});

function handleCandidate(candidate) {
  try {
    window.RTCIceCandidate =
      window.RTCIceCandidate ||
      window.mozRTCIceCandidate ||
      window.webkitRTCIceCandidate;
    peerConnection.addIceCandidate(new window.RTCIceCandidate(candidate));
  } catch (err) {
    logError(err.message);
  }
}
function handleAnswer(answer) {
  try {
    window.RTCSessionDescription =
      window.RTCSessionDescription ||
      window.mozRTCSessionDescription ||
      window.webkitRTCSessionDescription;
    peerConnection.setRemoteDescription(
      new window.RTCSessionDescription(answer)
    );
  } catch (err) {
    logError(err.message);
  }
}
function handleLogin(success, type, callback) {
  if (success === false) {
    alert("Ooops...try a different username");
  } else {
    navigator.mediaDevices
      .getUserMedia({ video: true, audio: true }) // Request both video and audio
      .then(function (myStream) {
        localVideo.srcObject = myStream; // Display local stream in a video element
        myStream.getTracks().forEach((track) => {
          console.log("track", track); // Log track details for debugging
          console.log("peerConnection", peerConnection); // Log peerConnection state for debugging
          if (peerConnection) {
            // Validate peerConnection is not null or undefined
            peerConnection.addTrack(track, myStream); // Add each track to the peer connection
          } else {
            console.error("PeerConnection is not initialized.");
          }
          callback("true");
        });
      })
      .catch(function (err) {
        console.error(err.message); // Log error message to console
      });
  }
} //type 1 mic and cam, type 2 only mic

// Add each track from the local stream to the peer connection

function disconnect() {
  socket.send(
    JSON.stringify({
      type: "disconnect",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: "disconnect",
    })
  );
  console.log("Function called by worker at", new Date());
}
function update_time_use() {
  socket.send(
    JSON.stringify({
      type: "update",
      user_guid: user_guid,
      room_guid: room_guid,
      user_type: user_type,
      data: "update",
    })
  );
  //console.log("Function called by worker at", new Date());
}

setInterval(update_time_use, 3000); // Call doSomething every 3 seconds
