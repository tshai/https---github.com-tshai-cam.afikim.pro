const localVideo = document.getElementById("localVideo");
const remoteVideo = document.getElementById("remoteVideo");
const params = new URLSearchParams(window.location.search);
const user_guid = params.get("user_guid"); // This gets '1' from your example URL
const room_guid = params.get("room_guid");
const user_type = params.get("user_type");
let localStream;
let peerConnection;
let userStartChat = 0;
let disconnectMessageShowen = false;
let audio;
let video;
const config = {
  iceServers: [
    { urls: "stun:stun.l.google.com:19302" },
    {
      urls: "turn:116.203.70.128:80?transport=udp",
      username: "test",
      credential: "test",
    },
    {
      urls: "turn:116.203.70.128:80?transport=tcp",
      username: "test",
      credential: "test",
    },
  ], // Google's public STUN server
};

// Initialize WebSocket connection
const socket = new WebSocket("wss://mifgashim.net:8080");

function connectChat() {
  peerConnection = new RTCPeerConnection(config);
  peerConnection.ontrack = function (event) {
    $("#localVideo").css("width", "25%");
    remoteVideo.srcObject = event.streams[0];
    remoteVideo.onloadedmetadata = function () {
      remoteVideo.play();
    };
  };
  handleLogin("true", 1, function (x) {
    if (x === "true") {
      socket.send(
        JSON.stringify({
          type: "startCall",
          user_guid: user_guid,
          room_guid: room_guid,
          user_type: user_type,
          data: "startCall",
        })
      );
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
        break;
      case "failed":
        console.log("The connection failed");
        break;
      case "closed":
        console.log("The connection closed");
        break;
    }
  };
}

$(document).ready(function () {});
async function handleOffer(offer, name) {
  try {
    window.RTCSessionDescription =
      window.RTCSessionDescription ||
      window.mozRTCSessionDescription ||
      window.webkitRTCSessionDescription;
    var RTCSessionDescription_ = new window.RTCSessionDescription(offer);
    await peerConnection.setRemoteDescription(RTCSessionDescription_);

    // Now that setRemoteDescription is complete, we can safely create an answer
    peerConnection
      .createAnswer()
      .then(function (answer) {
        peerConnection.setLocalDescription(answer);
        socket.send(
          JSON.stringify({
            type: "answer",
            user_guid: user_guid,
            room_guid: room_guid,
            user_type: user_type,
            data: answer,
          })
        );
      })
      .catch(function (error) {
        logError(error.message);
        console.log(error.message);
        alert("Error when creating an answer");
      });
  } catch (err) {
    console.log("228-" + err.message);
  }
}
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
  // if (userStartChat == "1") {
  const data = JSON.parse(event.data);
  if (typeof data.error != "undefined") {
    if (disconnectMessageShowen == false) {
      if (data.error === "sessionSts1") {
        disconnectMessageShowen = true;
        alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
      } else if (data.error === "session_finished") {
        disconnectMessageShowen = true;
        alert("מפגש זה הסתיים ולא מחוייב יותר . עליך ליזום מפגש חדש");
      } else if (data.error === "moreThanOneUser") {
        disconnectMessageShowen = true;
        alert("אין אפשרות לפתוח יותר מחלון אחד בו זמנית");
      } else if (data.error === "girlDiscounectInternet") {
        disconnectMessageShowen = true;
        alert("הבחורה ניתקה מהאינטרנט");
      } else if (data.error === "finishTime") {
        disconnectMessageShowen = true;
        alert("נגמר הזמן שלך");
      }
    }
    window.location.href = "/";
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
  } else if (data.type === "offer") {
    await handleOffer(data.data, data.type);
  } else if (data.type === "candidate") {
    handleCandidate(data.data);
  }
  // }
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
    navigator.getUserMedia =
      navigator.getUserMedia ||
      navigator.webkitGetUserMedia ||
      navigator.mozGetUserMedia ||
      navigator.mediaDevices;
    navigator.mediaDevices
      .getUserMedia({ video: video, audio: audio }) // Request both video and audio
      .then(function (myStream) {
        localVideo.srcObject = myStream; // Display local stream in a video element
        myStream.getTracks().forEach((track) => {
          if (peerConnection) {
            // Validate peerConnection is not null or undefined
            peerConnection.addTrack(track, myStream); // Add each track to the peer connection
          } else {
            console.error("PeerConnection is not initialized.");
          }
        });
        callback("true");
      })
      .catch(function (err) {
        console.error(err.message); // Log error message to console
      });
  }
} //type 1 mic and cam, type 2 only mic

// Starting a call (example)
async function startCall(num) {
  $("#startCallDiv").removeClass("d-flex").hide();
  $("#waiting_for_call_start").hide();
  $("#disconnect").show();

  if (num == 1) {
    video = { facingMode: "user" };
    audio = true;
  } else {
    video = false;
    audio = true;
  }
  connectChat();
}
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
}

setInterval(update_time_use, 3000); // Call doSomething every 3 seconds
